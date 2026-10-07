<?php

namespace App\Modules\SocialWidgets\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\SocialWidgets\Http\Resources\PublicSocialWidgetResource;
use App\Modules\SocialWidgets\Models\SocialWidget;
use App\Modules\SocialWidgets\Services\InstagramFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PublicSocialWidgetController extends Controller
{
    public const RENDERER_ENTRY = 'app/Modules/SocialWidgets/Resources/assets/feed-renderer.js';

    public const LOADER_ENTRY = 'app/Modules/SocialWidgets/Resources/assets/feed-loader.js';

    public const STYLES_ENTRY = 'app/Modules/SocialWidgets/Resources/assets/feed.css';

    public function loader(string $token): Response
    {
        $this->publishedWidget($token);

        return response($this->builtAsset(self::RENDERER_ENTRY)."\n".$this->builtAsset(self::LOADER_ENTRY), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function config(string $token, InstagramFeedService $feeds): JsonResponse
    {
        $widget = $this->publishedWidget($token);

        return (new PublicSocialWidgetResource(['widget' => $widget, 'feed' => $feeds->feedFor($widget)]))
            ->response()
            ->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'public, max-age=300',
            ]);
    }

    public function styles(): Response
    {
        return response($this->builtAsset(self::STYLES_ENTRY), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function options(): Response
    {
        return response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
            'Access-Control-Max-Age' => '86400',
        ]);
    }

    /**
     * Reads the compiled file through the Vite manifest so the embed works on any origin
     * without depending on CORS headers for /build assets.
     */
    protected function builtAsset(string $entry): string
    {
        $manifestPath = public_path('build/manifest.json');
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
        $file = $manifest[$entry]['file'] ?? null;

        abort_unless($file && is_file(public_path('build/'.$file)), 404);

        return (string) file_get_contents(public_path('build/'.$file));
    }

    protected function publishedWidget(string $token): SocialWidget
    {
        return SocialWidget::query()
            ->where('public_token', $token)
            ->where('status', 'published')
            ->firstOrFail();
    }
}
