<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Models\StorefrontMenu;
use App\Modules\Commerce\Storefront\Services\CmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    public function settings(Request $r, CmsService $cms): JsonResponse
    {
        return response()->json(['data' => $cms->settings($r->attributes->get('store_workspace')->id)]);
    }

    public function menu(Request $r, string $slot, CmsService $cms): JsonResponse
    {
        abort_unless(in_array($slot, ['header', 'footer', 'mobile']), 404);
        $record = StorefrontMenu::query()
            ->where('workspace_id', $r->attributes->get('store_workspace')->id)
            ->where('slot', $slot)
            ->first();

        return response()->json(['data' => $record ? ($record->items ?? []) : $cms->defaultMenuItems($slot)]);
    }

    public function page(Request $r, string $slug, CmsService $cms): JsonResponse
    {
        $w = $r->attributes->get('store_workspace')->id;
        $preview = $r->filled('preview_token');
        abort_if($preview && ! $cms->verifyPreview($w, $slug, $r->query('preview_token')), 403);

        return response()->json(['data' => $cms->page($w, $slug, $preview)])->header('Cache-Control', $preview ? 'private, no-store' : 'no-cache');
    }
}
