<?php

namespace App\Modules\MetaSocial\Services;

use App\Models\User;
use App\Modules\Commerce\AiTools\ChatbotProductPresenter;
use App\Modules\Commerce\Models\Product;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\MetaSocial\Jobs\PublishMetaPagePostJob;
use App\Modules\MetaSocial\Models\MetaPagePost;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Publishes posts to a Facebook Page (pages_manage_posts) and an Instagram account
 * (instagram_content_publish), now or at a scheduled time.
 */
class MetaPagePostService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected MetaSocialClient $client,
        protected MetaPageTokenResolver $pageTokens,
        protected MetaErrorTranslator $errors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function context(?User $user): array
    {
        $workspaceId = (int) $this->workspaces->current($user)->id;

        return [
            'pages' => $this->pageTokens->pages($workspaceId),
            'instagram_accounts' => $this->pageTokens->instagramAccounts($workspaceId),
            'products' => Product::query()->where('workspace_id', $workspaceId)->latest('id')->limit(200)->get(['id', 'name'])->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name])->all(),
            'posts' => MetaPagePost::query()->where('workspace_id', $workspaceId)->latest()->limit(50)->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(?User $user, array $data): MetaPagePost
    {
        $workspaceId = (int) $this->workspaces->current($user)->id;
        $targets = array_values(array_intersect(MetaPagePost::TARGETS, (array) $data['targets']));

        if (! collect($this->pageTokens->pages($workspaceId))->contains('id', (string) $data['page_id'])) {
            throw ValidationException::withMessages(['page_id' => __('Choose a Facebook Page connected to this workspace.')]);
        }

        $instagramId = in_array('instagram', $targets, true) ? (string) ($data['instagram_account_id'] ?? '') : null;

        if ($instagramId !== null && ! collect($this->pageTokens->instagramAccounts($workspaceId))->contains('id', $instagramId)) {
            throw ValidationException::withMessages(['instagram_account_id' => __('Choose an Instagram account connected to this workspace.')]);
        }

        $message = trim((string) ($data['message'] ?? ''));
        $link = filled($data['link'] ?? null) ? (string) $data['link'] : null;
        $imageUrl = filled($data['image_url'] ?? null) ? (string) $data['image_url'] : null;
        $productId = null;

        if (filled($data['media_id'] ?? null)) {
            $media = Media::query()->find((int) $data['media_id']);

            if (! $media?->isImage()) {
                throw ValidationException::withMessages(['media_id' => __('Choose an image from the media library.')]);
            }

            $imageUrl = str_starts_with((string) $media->url, 'http') ? (string) $media->url : url((string) $media->url);
        }

        if (filled($data['product_id'] ?? null)) {
            $product = Product::query()->where('workspace_id', $workspaceId)->find((int) $data['product_id']);

            if (! $product) {
                throw ValidationException::withMessages(['product_id' => __('Choose a product from your store.')]);
            }

            $presenter = app(ChatbotProductPresenter::class, ['workspaceId' => $workspaceId]);
            $showcase = $presenter->showcase($product->loadMissing(['colors.swatchMedia', 'gallery.media', 'primaryMedia']));
            $productId = $product->id;
            $link ??= $showcase['url'];
            $imageUrl ??= $showcase['colors'][0]['image_url'] ?? null;
            $message = $message !== '' ? $message : trim($product->name."\n".($presenter->priceLines($product)[0] ?? ''));
        }

        if ($message === '') {
            throw ValidationException::withMessages(['message' => __('Write the post text or choose a product.')]);
        }

        if ($instagramId !== null && blank($imageUrl)) {
            throw ValidationException::withMessages(['image_url' => __('Instagram posts need a photo. Add an image or choose a product with photos.')]);
        }

        $scheduledAt = filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : null;

        $post = MetaPagePost::query()->create([
            'workspace_id' => $workspaceId,
            'created_by' => $user?->id,
            'page_id' => (string) $data['page_id'],
            'instagram_account_id' => $instagramId,
            'targets' => $targets,
            'message' => $message,
            'link' => $link,
            'image_url' => $imageUrl,
            'product_id' => $productId,
            'scheduled_at' => $scheduledAt,
            'status' => MetaPagePost::STATUS_SCHEDULED,
        ]);

        if ($scheduledAt === null || $scheduledAt->isPast()) {
            PublishMetaPagePostJob::dispatch($post->id);
        }

        return $post->refresh();
    }

    /**
     * Publishes to each target on its own, so one failing does not block the other.
     */
    public function publish(MetaPagePost $post): MetaPagePost
    {
        $post->update(['status' => MetaPagePost::STATUS_PUBLISHING]);
        $errors = [];
        $done = [];

        try {
            $token = $this->pageTokens->forPage((int) $post->workspace_id, (string) $post->page_id);
        } catch (Throwable $exception) {
            $post->update(['status' => MetaPagePost::STATUS_FAILED, 'errors' => ['facebook' => $this->messageFrom($exception)]]);

            return $post->refresh();
        }

        if ($post->postsTo('facebook') && blank($post->facebook_post_id)) {
            try {
                $response = filled($post->image_url)
                    ? $this->client->publishPagePhoto((string) $post->page_id, $token, (string) $post->image_url, $post->caption())
                    : $this->client->publishPagePost((string) $post->page_id, $token, (string) $post->message, $post->link);
                $json = $this->check($response);
                $post->facebook_post_id = (string) ($json['post_id'] ?? $json['id']);
                $done[] = 'facebook';
            } catch (Throwable $exception) {
                $errors['facebook'] = $this->messageFrom($exception);
            }
        }

        if ($post->postsTo('instagram') && blank($post->instagram_media_id)) {
            try {
                $container = $this->check($this->client->createInstagramMedia((string) $post->instagram_account_id, $token, (string) $post->image_url, $post->caption()));
                $published = $this->check($this->client->publishInstagramMedia((string) $post->instagram_account_id, $token, (string) $container['id']));
                $post->instagram_media_id = (string) $published['id'];
                $done[] = 'instagram';
            } catch (Throwable $exception) {
                $errors['instagram'] = $this->messageFrom($exception);
            }
        }

        $succeeded = collect($post->targetList())->filter(fn (string $target): bool => filled($target === 'facebook' ? $post->facebook_post_id : $post->instagram_media_id));

        $post->fill([
            'errors' => $errors === [] ? null : $errors,
            'status' => match (true) {
                $errors === [] => MetaPagePost::STATUS_PUBLISHED,
                $succeeded->isNotEmpty() => MetaPagePost::STATUS_PARTIAL,
                default => MetaPagePost::STATUS_FAILED,
            },
            'published_at' => $succeeded->isNotEmpty() ? ($post->published_at ?? now()) : null,
        ])->save();

        return $post->refresh();
    }

    /**
     * Sends every scheduled post whose time has come (run every minute by the scheduler).
     */
    public function publishDue(): int
    {
        $due = MetaPagePost::query()
            ->where('status', MetaPagePost::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        $due->each(fn (MetaPagePost $post) => $this->publish($post));

        return $due->count();
    }

    /**
     * Sends a failed or partly published post again, only to the targets that did not work.
     */
    public function retry(MetaPagePost $post): MetaPagePost
    {
        if (! in_array($post->status, [MetaPagePost::STATUS_FAILED, MetaPagePost::STATUS_PARTIAL], true)) {
            throw ValidationException::withMessages(['post' => __('Only failed posts can be sent again.')]);
        }

        return $this->publish($post);
    }

    /**
     * Deletes the Facebook post (Instagram does not allow deleting posts through the API) and our record.
     */
    public function delete(MetaPagePost $post): void
    {
        if (filled($post->facebook_post_id)) {
            $this->check($this->client->deletePagePost((string) $post->facebook_post_id, $this->pageTokens->forPage((int) $post->workspace_id, (string) $post->page_id)));
        }

        $post->delete();
    }

    /**
     * What is on the Page and the Instagram account right now.
     *
     * @return array{facebook: array<int, array<string, mixed>>, instagram: array<int, array<string, mixed>>, error: ?string}
     */
    public function recent(?User $user, string $pageId, ?string $instagramId): array
    {
        $workspaceId = (int) $this->workspaces->current($user)->id;
        $result = ['facebook' => [], 'instagram' => [], 'error' => null];

        if (! collect($this->pageTokens->pages($workspaceId))->contains('id', $pageId)) {
            return $result;
        }

        try {
            $token = $this->pageTokens->forPage($workspaceId, $pageId);
            $result['facebook'] = collect((array) data_get($this->check($this->client->pagePublishedPosts($pageId, $token)), 'data', []))
                ->map(fn (array $post): array => [
                    'message' => mb_strimwidth((string) ($post['message'] ?? ''), 0, 160, '…'),
                    'picture' => $post['full_picture'] ?? null,
                    'url' => $post['permalink_url'] ?? null,
                    'created_time' => $post['created_time'] ?? null,
                ])->all();

            if (filled($instagramId) && collect($this->pageTokens->instagramAccounts($workspaceId))->contains('id', $instagramId)) {
                $result['instagram'] = collect((array) data_get($this->check($this->client->instagramMedia((string) $instagramId, $token)), 'data', []))
                    ->map(fn (array $media): array => [
                        'message' => mb_strimwidth((string) ($media['caption'] ?? ''), 0, 160, '…'),
                        'picture' => $media['media_url'] ?? $media['thumbnail_url'] ?? null,
                        'url' => $media['permalink'] ?? null,
                        'created_time' => $media['timestamp'] ?? null,
                    ])->all();
            }
        } catch (Throwable $exception) {
            $result['error'] = $this->messageFrom($exception);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function check(Response $response): array
    {
        $json = (array) ($response->json() ?? []);

        if ($response->successful() && ! isset($json['error'])) {
            return $json;
        }

        $message = $this->errors->summary(['error' => (array) ($json['error'] ?? ['message' => __('Meta request failed.')])]);
        $userMessage = data_get($json, 'error.error_user_msg');

        throw new RuntimeException(trim($message.(filled($userMessage) && ! str_contains($message, (string) $userMessage) ? ' '.$userMessage : '')));
    }

    protected function messageFrom(Throwable $exception): string
    {
        return $exception instanceof ValidationException
            ? (string) collect($exception->errors())->flatten()->first()
            : mb_substr($exception->getMessage(), 0, 500);
    }
}
