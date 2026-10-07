<?php

namespace App\Modules\SocialWidgets\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\SocialWidgets\Enums\SocialWidgetLayout;
use App\Modules\SocialWidgets\Http\Requests\StoreSocialWidgetRequest;
use App\Modules\SocialWidgets\Models\SocialWidget;
use App\Modules\SocialWidgets\Support\SocialWidgetSettings;
use App\Modules\SocialWidgets\Support\WidgetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialWidgetController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function library(): View
    {
        $widgets = WidgetCatalog::widgets();

        return view('social-widgets::user.library', [
            'widgetCount' => count($widgets),
            'recommended' => array_values(array_filter($widgets, fn (array $widget): bool => $widget['recommended'])),
            'sections' => WidgetCatalog::sections(),
        ]);
    }

    public function index(Request $request, string $provider): View
    {
        $widgets = SocialWidget::query()
            ->where('workspace_id', $this->workspaces->current($request->user())->id)
            ->where('provider', $provider)
            ->latest()
            ->get();

        return view('social-widgets::user.index', [
            'provider' => $provider,
            'providerLabel' => $this->providerLabel($provider),
            'widgets' => $widgets,
        ]);
    }

    public function layouts(string $provider): View
    {
        $recommended = SocialWidgetLayout::recommendedFor($provider);

        return view('social-widgets::user.layouts', [
            'provider' => $provider,
            'providerLabel' => $this->providerLabel($provider),
            'recommended' => $recommended,
            'otherLayouts' => array_values(array_filter(SocialWidgetLayout::cases(), fn (SocialWidgetLayout $layout): bool => $layout !== $recommended)),
            'layoutCount' => count(SocialWidgetLayout::cases()),
        ]);
    }

    public function store(StoreSocialWidgetRequest $request, string $provider): RedirectResponse
    {
        $widget = SocialWidget::create([
            'workspace_id' => $this->workspaces->current($request->user())->id,
            'provider' => $provider,
            'name' => __('Untitled :label', ['label' => $this->providerLabel($provider)]),
            'layout' => $request->validated('layout'),
            'settings' => SocialWidgetSettings::defaults(),
        ]);

        return redirect()->route('user.social-widgets.edit', $widget);
    }

    public function destroy(Request $request, SocialWidget $widget): RedirectResponse
    {
        $this->authorizeWorkspace($request, $widget);
        $provider = $widget->provider;
        $widget->delete();

        return redirect()->route('user.social-widgets.index', $provider)->with('success', __('Widget deleted.'));
    }

    protected function authorizeWorkspace(Request $request, SocialWidget $widget): void
    {
        abort_unless($widget->workspace_id === $this->workspaces->current($request->user())->id, 404);
    }

    protected function providerLabel(string $provider): string
    {
        return match ($provider) {
            'instagram' => 'Instagram Feed',
            default => ucfirst($provider).' Feed',
        };
    }
}
