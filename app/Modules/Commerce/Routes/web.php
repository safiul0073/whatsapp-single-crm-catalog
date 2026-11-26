<?php

use App\Modules\Commerce\Http\Controllers\PublicCommerceController;
use App\Modules\Commerce\Http\Controllers\PublicInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('commerce/catalog/{token}/feed.csv', [PublicCommerceController::class, 'feed'])->name('commerce.catalog.feed');

Route::get('invoices/{token}', [PublicInvoiceController::class, 'show'])->middleware('throttle:60,1')->name('commerce.invoices.show');
Route::get('custom-invoices/{token}', [PublicInvoiceController::class, 'custom'])->middleware('throttle:60,1')->name('commerce.custom-invoices.show');
Route::get('packing-slips/{order}', [PublicInvoiceController::class, 'packingSlip'])->whereNumber('order')->middleware(['signed', 'throttle:60,1'])->name('commerce.packing-slips.show');
