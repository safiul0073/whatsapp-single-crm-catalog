<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Resources\MenuBrandResource;
use App\Modules\Commerce\Http\Resources\MenuCategoryResource;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuApiController extends Controller
{
    /**
     * Storefront mega menu: active root categories with two levels of children, plus active brands.
     * Workspace resolution mirrors ProductApiController::filters().
     */
    public function index(Request $request): JsonResponse
    {
        $workspaceId = $request->integer('workspace_id', 1);
        $activeChildren = fn ($query) => $query->where('is_active', true)->orderBy('name');

        $categories = Category::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $activeChildren($query)->with(['children' => $activeChildren])])
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => MenuCategoryResource::collection($categories)->resolve($request),
                'brands' => MenuBrandResource::collection($brands)->resolve($request),
            ],
        ]);
    }
}
