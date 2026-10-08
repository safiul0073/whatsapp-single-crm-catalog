<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\ProductShowcaseEstimateRequest;
use App\Modules\Commerce\Services\ProductShowcaseService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductShowcaseController extends Controller
{
    public function show(Request $request, string $slug, ProductShowcaseService $showcase): JsonResponse
    {
        $product = $showcase->find($request->attributes->get('store_workspace'), $slug);

        return response()->json(['data' => $showcase->payload($product)])->header('Cache-Control', 'no-store');
    }

    public function stockQuote(Request $request, string $slug, ProductShowcaseService $showcase, UnifiedOrderService $orders): JsonResponse
    {
        $validated = $request->validate(
            ['country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'], 'shipping_method_id' => ['nullable', 'integer', 'min:1']],
            ['country.required' => 'Select a delivery country.', 'country.regex' => 'Select a valid delivery country.'],
        );
        $workspace = $request->attributes->get('store_workspace');
        $product = $showcase->find($workspace, $slug);

        return response()->json(['data' => $showcase->stockQuote($workspace, $product, $validated['country'], $validated['shipping_method_id'] ?? null, $orders)])
            ->header('Cache-Control', 'no-store');
    }

    public function estimate(ProductShowcaseEstimateRequest $request, string $slug, ProductShowcaseService $showcase, UnifiedOrderService $orders): JsonResponse
    {
        $workspace = $request->attributes->get('store_workspace');
        $product = $showcase->find($workspace, $slug);
        $data = $request->validated();
        $data['groups'] = array_map(fn ($group) => [...$group, 'product_id' => $product->id], $data['groups']);
        $data['shipping_address'] = ['country' => $data['country']];
        try {
            $estimate = $orders->preview($workspace, $data);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => $exception->errors(), 'product' => $showcase->payload($showcase->find($workspace, $slug))], 422)->header('Cache-Control', 'no-store');
        }
        $stock = $showcase->payload($showcase->find($workspace, $slug));
        if (collect($estimate['availability'])->contains(fn ($item) => $item['required'] > $item['available'])) {
            return response()->json(['message' => 'Availability has changed. Reduce the selected quantities.', 'product' => $stock], 422)->header('Cache-Control', 'no-store');
        }

        return response()->json(['data' => $estimate, 'product' => $stock])->header('Cache-Control', 'no-store');
    }
}
