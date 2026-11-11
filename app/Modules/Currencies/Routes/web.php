<?php

use App\Modules\Currencies\Http\Requests\SwitchCurrencyRequest;
use App\Modules\Currencies\Services\VisitorCurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::post('currency', function (SwitchCurrencyRequest $request): RedirectResponse {
    return back()->withCookie(cookie()->forever(VisitorCurrencyService::COOKIE, $request->validated('code')));
})->name('currency.switch');
