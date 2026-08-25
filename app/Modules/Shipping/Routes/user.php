<?php

use App\Modules\Shipping\Http\Controllers\User\ShippingController;
use Illuminate\Support\Facades\Route;

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

Route::resource('shipping/methods', App\Modules\Shipping\Http\Controllers\User\ShippingMethodController::class)
    ->names('shipping.methods')
    ->except(['show']);
