<?php

namespace App\Modules\TikTok\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\TikTok\Jobs\PublishTikTokPostJob;
use App\Modules\TikTok\Models\TikTokPost;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Posts videos to TikTok with the Content Posting API: directly to the profile (video.publish) or to the
 * creator's TikTok inbox as a draft they finish in the app (video.upload).
 */
class TikTokPostService
{
    public const DISK = 'local';

    /** TikTok uploads: files up to 64 MB go in one chunk; bigger files use 10 MB chunks, the last one taking the rest. */
    protected const SINGLE_CHUNK_LIMIT = 64 * 1024 * 1024;

    protected const CHUNK_SIZE = 10 * 1024 * 1024;

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected TikTokClient $client,
        protected TikTokTokenService $tokens,
    ) {}

    /**
     * @return Collection<int, ChannelAccount>
     */
    public function accounts(?User $user): Collection
    {
        return ChannelAccount::query()
            ->where('workspace_id', $this->workspaces->current($user)->id)
            ->where('provider', 'tiktok')
            ->where('status', ChannelAccountStatus::Connected->value)
            ->latest('id')
            ->get();
    }

    public function canDirectPost(ChannelAccount $account): bool
    {
        return $this->hasScope($account, 'video.publish');
    }

    public function canSendDraft(ChannelAccount $account): bool
    {
        return $this->hasScope($account, 'video.upload');
    }

    /**
     * Fresh creator details, required by TikTok every time the "Post to TikTok" form is shown.
     *
     * @return array{nickname: string, username: string, avatar_url: ?string, privacy_level_options: array<int, string>, comment_disabled: bool, duet_disabled: bool, stitch_disabled: bool, max_video_post_duration_sec: int}
     */
    public function creatorInfo(ChannelAccount $account): array
    {
        $data = (array) data_get($this->check($this->client->creatorInfo($this->token($account))), 'data', []);

        return [
            'nickname' => (string) ($data['creator_nickname'] ?? $account->name),
            'username' => (string) ($data['creator_username'] ?? ''),
            'avatar_url' => $data['creator_avatar_url'] ?? null,
            'privacy_level_options' => array_values(array_intersect(TikTokPost::PRIVACY_LEVELS, (array) ($data['privacy_level_options'] ?? []))),
            'comment_disabled' => (bool) ($data['comment_disabled'] ?? false),
            'duet_disabled' => (bool) ($data['duet_disabled'] ?? false),
            'stitch_disabled' => (bool) ($data['stitch_disabled'] ?? false),
            'max_video_post_duration_sec' => (int) ($data['max_video_post_duration_sec'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(?User $user, array $data, UploadedFile $video): TikTokPost
    {
        $account = $this->accounts($user)->firstWhere('id', (int) $data['channel_account_id']);

        if (! $account) {
            throw ValidationException::withMessages(['channel_account_id' => __('Choose a TikTok account connected to this workspace.')]);
        }

        $mode = (string) $data['mode'];
        $fields = ['title' => filled($data['title'] ?? null) ? (string) $data['title'] : null];

        if ($mode === TikTokPost::MODE_DIRECT) {
            if (! $this->canDirectPost($account)) {
                throw ValidationException::withMessages(['mode' => __('Reconnect TikTok and allow posting (video.publish) to post directly.')]);
            }

            try {
                $creator = $this->creatorInfo($account);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['channel_account_id' => $exception->getMessage()]);
            }

            $privacy = (string) ($data['privacy_level'] ?? '');

            if (! in_array($privacy, $creator['privacy_level_options'], true)) {
                throw ValidationException::withMessages(['privacy_level' => __('Choose who can see this video.')]);
            }

            $disclose = (bool) ($data['disclose_commercial'] ?? false);
            $brandOrganic = $disclose && (bool) ($data['brand_organic'] ?? false);
            $brandContent = $disclose && (bool) ($data['brand_content'] ?? false);

            if ($disclose && ! $brandOrganic && ! $brandContent) {
                throw ValidationException::withMessages(['disclose_commercial' => __('You need to indicate if your content promotes yourself, a third party, or both.')]);
            }

            if ($brandContent && $privacy === 'SELF_ONLY') {
                throw ValidationException::withMessages(['privacy_level' => __('Branded content visibility cannot be set to private.')]);
            }

            $fields += [
                'privacy_level' => $privacy,
                'allow_comment' => ! $creator['comment_disabled'] && (bool) ($data['allow_comment'] ?? false),
                'allow_duet' => ! $creator['duet_disabled'] && (bool) ($data['allow_duet'] ?? false),
                'allow_stitch' => ! $creator['stitch_disabled'] && (bool) ($data['allow_stitch'] ?? false),
                'brand_organic' => $brandOrganic,
                'brand_content' => $brandContent,
                'is_aigc' => (bool) ($data['is_aigc'] ?? false),
            ];
        } elseif (! $this->canSendDraft($account)) {
            throw ValidationException::withMessages(['mode' => __('Reconnect TikTok and allow video uploads (video.upload).')]);
        }

        $workspaceId = (int) $account->workspace_id;
        $path = $video->store('tiktok-posts/'.$workspaceId, self::DISK);

        $post = TikTokPost::query()->create($fields + [
            'workspace_id' => $workspaceId,
            'created_by' => $user?->id,
            'channel_account_id' => $account->id,
            'mode' => $mode,
            'product_id' => filled($data['product_id'] ?? null) ? (int) $data['product_id'] : null,
            'video_path' => $path,
            'video_mime' => $this->mimeType($video),
            'video_size' => (int) $video->getSize(),
            'status' => TikTokPost::STATUS_QUEUED,
        ]);

        PublishTikTokPostJob::dispatch($post->id);

        return $post->refresh();
    }

    /**
     * Starts the post with TikTok and uploads the file in chunks.
     */
    public function publish(TikTokPost $post): TikTokPost
    {
        try {
            $account = $post->channelAccount ?? throw new RuntimeException(__('The TikTok account was disconnected.'));
            $token = $this->token($account);
            $disk = Storage::disk(self::DISK);

            if (! $post->video_path || ! $disk->exists($post->video_path)) {
                throw new RuntimeException(__('The video file is missing. Upload it again.'));
            }

            $size = (int) $disk->size($post->video_path);

            if ($size <= 0) {
                throw new RuntimeException(__('The video file is empty. Upload it again.'));
            }
            $plan = $this->chunkPlan($size);

            $response = $post->mode === TikTokPost::MODE_DIRECT
                ? $this->client->initDirectPost($token, $this->postInfo($post), $plan)
                : $this->client->initInboxUpload($token, $plan);
            $data = (array) data_get($this->check($response), 'data', []);
            $publishId = (string) ($data['publish_id'] ?? '');
            $uploadUrl = (string) ($data['upload_url'] ?? '');

            if ($publishId === '' || $uploadUrl === '') {
                throw new RuntimeException(__('TikTok did not return an upload address.'));
            }

            $post->update(['publish_id' => $publishId]);
            $this->upload($disk->path($post->video_path), $uploadUrl, $size, $plan, (string) $post->video_mime);
            $post->update(['status' => TikTokPost::STATUS_PROCESSING, 'fail_reason' => null]);
        } catch (Throwable $exception) {
            $this->fail($post, $exception->getMessage());
        }

        return $post->refresh();
    }

    /**
     * Asks TikTok how the post is doing and records the result.
     */
    public function checkStatus(TikTokPost $post): TikTokPost
    {
        if ($post->status !== TikTokPost::STATUS_PROCESSING || blank($post->publish_id) || ! $post->channelAccount) {
            return $post;
        }

        try {
            $data = (array) data_get($this->check($this->client->postStatus($this->token($post->channelAccount), (string) $post->publish_id)), 'data', []);
        } catch (Throwable) {
            return $post;
        }

        match ((string) ($data['status'] ?? '')) {
            'PUBLISH_COMPLETE' => $this->finish($post, [
                'status' => TikTokPost::STATUS_PUBLISHED,
                'published_at' => now(),
                'tiktok_post_id' => isset($data['publicaly_available_post_id'][0]) ? (string) $data['publicaly_available_post_id'][0] : null,
            ]),
            'SEND_TO_USER_INBOX' => $this->finish($post, ['status' => TikTokPost::STATUS_IN_INBOX]),
            'FAILED' => $this->fail($post, $this->failReason((string) ($data['fail_reason'] ?? ''))),
            default => null,
        };

        return $post->refresh();
    }

    /**
     * Run every minute by the scheduler.
     */
    public function refreshPending(): int
    {
        $pending = TikTokPost::query()->with('channelAccount')->where('status', TikTokPost::STATUS_PROCESSING)->limit(25)->get();
        $pending->each(fn (TikTokPost $post) => $this->checkStatus($post));

        return $pending->count();
    }

    public function delete(TikTokPost $post): void
    {
        if ($post->video_path) {
            Storage::disk(self::DISK)->delete($post->video_path);
        }

        $post->delete();
    }

    /**
     * @return array{video_size: int, chunk_size: int, total_chunk_count: int}
     */
    public function chunkPlan(int $size): array
    {
        if ($size <= self::SINGLE_CHUNK_LIMIT) {
            return ['video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1];
        }

        return ['video_size' => $size, 'chunk_size' => self::CHUNK_SIZE, 'total_chunk_count' => intdiv($size, self::CHUNK_SIZE)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function postInfo(TikTokPost $post): array
    {
        return array_filter([
            'title' => $post->title,
            'privacy_level' => $post->privacy_level,
            'disable_comment' => ! $post->allow_comment,
            'disable_duet' => ! $post->allow_duet,
            'disable_stitch' => ! $post->allow_stitch,
            'brand_organic_toggle' => $post->brand_organic,
            'brand_content_toggle' => $post->brand_content,
            'is_aigc' => $post->is_aigc,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  array{video_size: int, chunk_size: int, total_chunk_count: int}  $plan
     */
    protected function upload(string $path, string $uploadUrl, int $size, array $plan, string $mimeType): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(__('The video file could not be read.'));
        }

        try {
            for ($index = 0; $index < $plan['total_chunk_count']; $index++) {
                $first = $index * $plan['chunk_size'];
                $length = $index === $plan['total_chunk_count'] - 1 ? $size - $first : $plan['chunk_size'];
                fseek($handle, $first);
                $bytes = (string) fread($handle, $length);
                $response = $this->client->uploadChunk($uploadUrl, $bytes, $first, $size, $mimeType);

                if (! $response->successful()) {
                    throw new RuntimeException(__('TikTok rejected the upload (HTTP :status).', ['status' => $response->status()]));
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function finish(TikTokPost $post, array $fields): void
    {
        if ($post->video_path) {
            Storage::disk(self::DISK)->delete($post->video_path);
        }

        $post->update($fields + ['video_path' => null, 'fail_reason' => null]);
    }

    protected function fail(TikTokPost $post, string $reason): void
    {
        $post->update(['status' => TikTokPost::STATUS_FAILED, 'fail_reason' => mb_substr($reason, 0, 1000)]);
    }

    protected function failReason(string $code): string
    {
        return match ($code) {
            'file_format_check_failed' => __('TikTok could not read this video format. Use MP4, MOV or WebM.'),
            'duration_check_failed' => __('The video is longer than this account is allowed to post.'),
            'frame_rate_check_failed' => __('The video frame rate is not supported by TikTok.'),
            'picture_size_check_failed' => __('The video resolution is not supported by TikTok.'),
            'spam_risk_too_many_posts' => __('This TikTok account reached its daily post limit. Try again tomorrow.'),
            'spam_risk_user_banned_from_posting' => __('This TikTok account is not allowed to post right now.'),
            'video_pull_failed', 'internal' => __('TikTok had a problem processing the video. Try again.'),
            default => $code !== '' ? __('TikTok could not post the video (:reason).', ['reason' => $code]) : __('TikTok could not post the video.'),
        };
    }

    protected function token(ChannelAccount $account): string
    {
        $token = $this->tokens->accessToken($account);

        if (blank($token)) {
            throw new RuntimeException(__('TikTok access has expired. Reconnect TikTok.'));
        }

        return (string) $token;
    }

    protected function hasScope(ChannelAccount $account, string $scope): bool
    {
        return in_array($scope, array_map('trim', explode(',', (string) data_get($account->settings, 'scope', ''))), true);
    }

    protected function mimeType(UploadedFile $video): string
    {
        return match (strtolower($video->getClientOriginalExtension())) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => 'video/mp4',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function check(Response $response): array
    {
        $json = (array) ($response->json() ?? []);
        $code = (string) data_get($json, 'error.code', $response->successful() ? 'ok' : 'error');

        if ($response->successful() && in_array($code, ['ok', ''], true)) {
            return $json;
        }

        throw new RuntimeException(match ($code) {
            'access_token_invalid' => __('TikTok access has expired. Reconnect TikTok.'),
            'scope_not_authorized' => __('Reconnect TikTok and allow posting videos.'),
            'spam_risk_too_many_posts' => __('This TikTok account reached its daily post limit. Try again tomorrow.'),
            'spam_risk_too_many_pending_share' => __('TikTok allows at most 5 drafts waiting in the inbox per day. Finish or delete some in the TikTok app.'),
            'spam_risk_user_banned_from_posting' => __('This TikTok account is not allowed to post right now.'),
            'reached_active_user_cap' => __('TikTok\'s daily posting limit for this app was reached. Try again tomorrow.'),
            'rate_limit_exceeded' => __('Too many TikTok requests. Wait a minute and try again.'),
            default => (string) (data_get($json, 'error.message') ?: __('TikTok request failed.')),
        });
    }
}
