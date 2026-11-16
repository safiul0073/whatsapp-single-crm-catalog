<?php

use App\Modules\Shipping\Http\Controllers\User\AgentController;
use App\Modules\Shipping\Http\Controllers\User\BillingController;
use App\Modules\Shipping\Http\Controllers\User\CarrierAccountController;
use App\Modules\Shipping\Http\Controllers\User\QuoteController;
use App\Modules\Shipping\Http\Controllers\User\RemoteAreaController;
use App\Modules\Shipping\Http\Controllers\User\ReportController;
use App\Modules\Shipping\Http\Controllers\User\ShipmentController;
use App\Modules\Shipping\Http\Controllers\User\ShippingController;
use App\Modules\Shipping\Http\Controllers\User\ShippingMethodController;
use App\Modules\Shipping\Http\Controllers\User\ToolsController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:shipping.manage')->group(function () {
    Route::get('shipping', [ShippingController::class, 'index'])->name('shipping.index');
    Route::get('shipping/settings', [ShippingController::class, 'settings'])->name('shipping.settings');
    Route::put('shipping/settings', [ShippingController::class, 'updateSettings'])->name('shipping.settings.update');
    Route::get('shipping/zones/create', [ShippingController::class, 'createZone'])->name('shipping.zones.create');
    Route::post('shipping/zones', [ShippingController::class, 'storeZone'])->name('shipping.zones.store');
    Route::get('shipping/zones/{zone}/edit', [ShippingController::class, 'editZone'])->name('shipping.zones.edit');
    Route::put('shipping/zones/{zone}', [ShippingController::class, 'updateZone'])->name('shipping.zones.update');
    Route::delete('shipping/zones/{zone}', [ShippingController::class, 'destroyZone'])->name('shipping.zones.destroy');
    Route::post('shipping/zones/{zone}/rates', [ShippingController::class, 'storeRate'])->name('shipping.rates.store');
    Route::put('shipping/rates/{rate}', [ShippingController::class, 'updateRate'])->name('shipping.rates.update');
    Route::delete('shipping/rates/{rate}', [ShippingController::class, 'destroyRate'])->name('shipping.rates.destroy');

    Route::resource('shipping/methods', ShippingMethodController::class)
        ->names('shipping.methods')
        ->except(['show']);
});

Route::prefix('shipping')->name('shipping.')->group(function () {
    Route::middleware('permission:shipping.shipments.view')->group(function () {
        Route::get('overview', [ShipmentController::class, 'overview'])->name('overview');
        Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('remote-areas', [RemoteAreaController::class, 'index'])->name('remote-areas.index');
        Route::get('calculator', [ToolsController::class, 'calculator'])->name('calculator');
        Route::post('calculator', [ToolsController::class, 'calculate'])->name('calculator.calculate');
    });

    Route::middleware('permission:shipping.shipments.manage')->group(function () {
        Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::put('shipments/{shipment}', [ShipmentController::class, 'update'])->name('shipments.update');
        Route::put('shipments/{shipment}/cartons', [ShipmentController::class, 'cartons'])->name('shipments.cartons');
        Route::put('shipments/{shipment}/booking', [ShipmentController::class, 'booking'])->name('shipments.booking');
        Route::put('shipments/{shipment}/status', [ShipmentController::class, 'status'])->name('shipments.status');
    });

    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:shipping.shipments.view')->name('shipments.show');

    Route::get('comparison', [ToolsController::class, 'comparison'])->middleware('permission:shipping.costs.view')->name('comparison');

    Route::middleware('permission:shipping.quotes.manage')->group(function () {
        Route::post('shipments/{shipment}/rates', [QuoteController::class, 'requestRates'])->name('quotes.request');
        Route::post('shipments/{shipment}/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::put('quotes/{quote}/status', [QuoteController::class, 'status'])->name('quotes.status');
    });
    Route::post('shipments/{shipment}/quotes/{quote}/approve', [QuoteController::class, 'approve'])->middleware('permission:shipping.quotes.approve')->name('quotes.approve');

    Route::middleware('permission:shipping.agents.view')->group(function () {
        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('agents/{agent}', [AgentController::class, 'show'])->whereNumber('agent')->name('agents.show');
        Route::get('charts/{chart}/file', [AgentController::class, 'chartFile'])->name('charts.file');
    });
    Route::middleware('permission:shipping.agents.manage')->group(function () {
        Route::get('agents/create', [AgentController::class, 'create'])->name('agents.create');
        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::get('agents/{agent}/edit', [AgentController::class, 'edit'])->name('agents.edit');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
        Route::post('agents/{agent}/charts', [AgentController::class, 'storeChart'])->name('charts.store');
        Route::put('charts/{chart}/status', [AgentController::class, 'chartStatus'])->name('charts.status');
        Route::delete('charts/{chart}', [AgentController::class, 'destroyChart'])->name('charts.destroy');
        Route::post('charts/{chart}/rows', [AgentController::class, 'storeRow'])->name('charts.rows.store');
        Route::delete('chart-rows/{row}', [AgentController::class, 'destroyRow'])->name('charts.rows.destroy');
        Route::post('remote-areas', [RemoteAreaController::class, 'store'])->name('remote-areas.store');
        Route::delete('remote-areas/{area}', [RemoteAreaController::class, 'destroy'])->name('remote-areas.destroy');
    });

    Route::middleware('permission:shipping.carriers.manage')->group(function () {
        Route::resource('carriers', CarrierAccountController::class)->except(['show'])->parameters(['carriers' => 'carrier']);
    });

    Route::get('billing', [BillingController::class, 'index'])->middleware('permission:shipping.billing.view')->name('billing.index');
    Route::put('shipments/{shipment}/billing', [BillingController::class, 'update'])->middleware('permission:shipping.billing.manage')->name('billing.update');
    Route::put('shipments/{shipment}/review', [BillingController::class, 'review'])->middleware('permission:shipping.billing.approve')->name('billing.review');

    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:shipping.reports.view')->name('reports.index');
    Route::get('product-costs', [ReportController::class, 'productCosts'])->middleware('permission:shipping.product_costs.manage')->name('product-costs.index');
    Route::put('product-costs', [ReportController::class, 'updateProductCosts'])->middleware('permission:shipping.product_costs.manage')->name('product-costs.update');
    Route::put('orders/{order}/other-costs', [ReportController::class, 'updateOtherCosts'])->middleware('permission:shipping.product_costs.manage')->name('other-costs.update');
    Route::get('audit', [ReportController::class, 'audit'])->middleware('permission:shipping.audit.view')->name('audit.index');
});
