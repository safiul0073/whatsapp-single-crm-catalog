<?php

namespace App\Modules\SocialWidgets\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\SocialWidgets\Enums\SocialWidgetLayout;
use App\Modules\SocialWidgets\Enums\SocialWidgetTheme;
use App\Modules\SocialWidgets\Http\Requests\UpdateSocialWidgetRequest;
use App\Modules\SocialWidgets\Models\SocialWidget;
use App\Modules\SocialWidgets\Services\InstagramFeedService;
use App\Modules\SocialWidgets\Support\SocialWidgetSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SocialWidgetEditorController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected InstagramFeedService $feeds,
    ) {}

    public function edit(Request $request, SocialWidget $widget): View
    {
        $this->authorizeWorkspace($request, $widget);
        $account = $this->feeds->connectedAccount($widget);

        return view('social-widgets::user.editor', [
            'widget' => $widget,
            'editorConfig' => [
                'name' => $widget->name,
                'layout' => $widget->layout->value,
                'settings' => $widget->mergedSettings(),
                'feed' => $this->feeds->feedFor($widget),
                'isPublished' => $widget->isPublished(),
                'embedCode' => $this->embedCode($widget),
                'themes' => collect(SocialWidgetTheme::cases())->mapWithKeys(fn (SocialWidgetTheme $theme): array => [$theme->value => $theme->colors()])->all(),
                'urls' => [
                    'update' => route('user.social-widgets.update', $widget),
                    'publish' => route('user.social-widgets.publish', $widget),
                    'source' => route('user.social-widgets.source', $widget),
                    'styles' => route('widgets.feed.styles'),
                ],
            ],
            'layouts' => SocialWidgetLayout::cases(),
            'themes' => SocialWidgetTheme::cases(),
            'account' => $account,
            'connectUrl' => Route::has('user.meta-social.setup') ? route('user.meta-social.setup').'#instagram' : null,
            'backUrl' => route('user.social-widgets.index', $widget->provider),
        ]);
    }

    public function update(UpdateSocialWidgetRequest $request, SocialWidget $widget): JsonResponse
    {
        $this->authorizeWorkspace($request, $widget);

        $widget->update([
            'name' => $request->validated('name'),
            'layout' => $request->validated('layout'),
            'settings' => SocialWidgetSettings::merge($request->settings()),
        ]);

        return response()->json(['saved' => true, 'updated_at' => $widget->updated_at?->toIso8601String()]);
    }

    public function publish(Request $request, SocialWidget $widget): JsonResponse
    {
        $this->authorizeWorkspace($request, $widget);

        $widget->update(['status' => 'published', 'published_at' => $widget->published_at ?? now()]);

        return response()->json(['published' => true, 'embed_code' => $this->embedCode($widget)]);
    }

    public function source(Request $request, SocialWidget $widget): JsonResponse
    {
        $this->authorizeWorkspace($request, $widget);
        $validated = $request->validate(
            ['username' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/']],
            ['username.regex' => __('Enter an Instagram username like @yourbrand.')],
        );

        $settings = $widget->mergedSettings();
        data_set($settings, 'source.username', filled($validated['username'] ?? null) ? ltrim($validated['username'], '@') : null);
        $widget->update(['settings' => $settings]);

        $feed = $this->feeds->feedFor($widget);
        $hasAccount = $this->feeds->connectedAccount($widget) !== null;

        return response()->json([
            'feed' => $feed,
            'message' => match (true) {
                ! $hasAccount && filled($validated['username'] ?? null) => __('Connect an Instagram business account to search usernames. Showing sample posts for now.'),
                $feed['source'] === 'demo' => __('Showing sample posts.'),
                default => null,
            },
        ]);
    }

    protected function embedCode(SocialWidget $widget): string
    {
        return '<div data-social-feed="'.$widget->public_token.'"></div>'."\n"
            .'<script src="'.route('widgets.feed.loader', $widget->public_token).'" async></script>';
    }

    protected function authorizeWorkspace(Request $request, SocialWidget $widget): void
    {
        abort_unless($widget->workspace_id === $this->workspaces->current($request->user())->id, 404);
    }
}
