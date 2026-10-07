<?php

use App\Modules\SocialWidgets\Http\Controllers\Public\PublicSocialWidgetController;
use Illuminate\Support\Facades\Route;

Route::get('widgets/feed/styles.css', [PublicSocialWidgetController::class, 'styles'])->name('widgets.feed.styles');
Route::get('widgets/feed/{token}/loader.js', [PublicSocialWidgetController::class, 'loader'])->name('widgets.feed.loader');
Route::options('widgets/feed/{token}', [PublicSocialWidgetController::class, 'options'])->name('widgets.feed.options');
Route::get('widgets/feed/{token}', [PublicSocialWidgetController::class, 'config'])->middleware('throttle:120,1')->name('widgets.feed.config');
