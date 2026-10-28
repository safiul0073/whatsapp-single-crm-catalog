<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Http\Requests\PackTemplateRequest;
use App\Modules\Commerce\Storefront\Models\PackTemplate;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackTemplateAdminController extends Controller
{
    public function __construct(private WorkspaceResolver $workspaces) {}

    public function index(Request $r): View
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::wholesaleEnabled($w), 404);

        return view('commerce::user.pack-templates', [
            'templates' => PackTemplate::query()->where('workspace_id', $w)->orderBy('position')->get(),
            'products' => Product::query()->where('workspace_id', $w)
                ->where('ws_enabled', true)
                ->whereIn('selling_mode', ['wholesale', 'both'])
                ->with(['variants.color', 'primaryMedia', 'category'])
                ->orderBy('name')
                ->get(),
            'currency' => StoreOrderSetting::catalogCurrency($w),
        ]);
    }

    public function create(Request $r): View
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::wholesaleEnabled($w), 404);

        return view('commerce::user.pack-template-form', [
            'template' => null,
            'products' => Product::query()->where('workspace_id', $w)
                ->where('ws_enabled', true)
                ->whereIn('selling_mode', ['wholesale', 'both'])
                ->with(['variants.color', 'primaryMedia', 'category'])
                ->orderBy('name')
                ->get(),
            'currency' => StoreOrderSetting::catalogCurrency($w),
        ]);
    }

    public function edit(Request $r, int $id): View
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::wholesaleEnabled($w), 404);
        $template = PackTemplate::query()->where('workspace_id', $w)->findOrFail($id);

        return view('commerce::user.pack-template-form', [
            'template' => $template,
            'products' => Product::query()->where('workspace_id', $w)
                ->where('ws_enabled', true)
                ->whereIn('selling_mode', ['wholesale', 'both'])
                ->with(['variants.color', 'primaryMedia', 'category'])
                ->orderBy('name')
                ->get(),
            'currency' => StoreOrderSetting::catalogCurrency($w),
        ]);
    }

    public function save(PackTemplateRequest $r, ?int $id = null): RedirectResponse
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::wholesaleEnabled($w), 404);
        $template = $id ? PackTemplate::query()->where('workspace_id', $w)->findOrFail($id) : new PackTemplate(['workspace_id' => $w]);
        $data = $r->validated();
        unset($data['_edit_id']);
        $template->fill($data)->save();

        $selectedProducts = Product::query()->where('workspace_id', $w)->whereIn('id', $template->product_ids)->with('variants')->get();
        $inadequate = [];
        foreach ($selectedProducts as $product) {
            $qualifyingColors = $product->variants
                ->where('status', 'active')
                ->filter(fn ($v) => $v->color_id && $v->size)
                ->groupBy('color_id')
                ->filter(fn ($variants) => $variants->pluck('size')->unique()->count() >= $template->min_sizes)
                ->count();
            if ($qualifyingColors < $template->min_colors) {
                $inadequate[] = $product->name;
            }
        }

        $response = redirect()->route('user.commerce.pack-templates.index')->with('success', $id ? 'Custom pack template updated.' : 'Custom pack template created.');
        if ($inadequate !== []) {
            $response->with('warning', 'Warning: '.implode(', ', $inadequate).' currently do not have enough active colors/sizes to satisfy this template’s variety requirements.');
        }

        return $response;
    }

    public function destroy(Request $r, int $id): RedirectResponse
    {
        $w = $this->workspaces->current($r->user())->id;
        abort_unless(StoreFeatures::wholesaleEnabled($w), 404);
        $template = PackTemplate::query()->where('workspace_id', $w)->findOrFail($id);
        $template->delete();

        return redirect()->route('user.commerce.pack-templates.index')->with('success', 'Custom pack template deleted.');
    }
}
