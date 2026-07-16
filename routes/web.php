<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\FrontendPageController;
use App\Modules\Blogs\Http\Controllers\BlogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendPageController::class, 'home'])->name('home');
Route::get('frontend/theme/{theme}.css', [FrontendPageController::class, 'themeCss'])
    ->where('theme', '[A-Za-z0-9_-]+')
    ->name('frontend.theme-css');

// Guest Routes (Auth)
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');

    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

});

// Language Switcher
Route::post('/locale', function (Request $request) {
    $request->validate(['locale' => 'required|string|max:10']);

    $locale = $request->input('locale');

    // Verify a lang/{locale}.json file exists
    if (file_exists(lang_path("{$locale}.json"))) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch')->middleware('web');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'logout'])->name('logout');

    // Email Verification
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

// Marketing page named routes (resolved by CMS catch-all)
Route::get('features', [FrontendPageController::class, 'show'])->defaults('slug', 'features')->name('features');
Route::get('faqs', [FrontendPageController::class, 'show'])->defaults('slug', 'faqs')->name('faqs');
Route::get('contact', [FrontendPageController::class, 'show'])->defaults('slug', 'contact')->name('contact');
Route::get('support', [FrontendPageController::class, 'show'])->defaults('slug', 'support')->name('support');
Route::get('blog', [FrontendPageController::class, 'show'])->defaults('slug', 'blog')->name('blog.index');
Route::get('blog/{blog:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('{slug}', [FrontendPageController::class, 'show'])
    ->where('slug', '^(?!admin$|dashboard$|login$|register$|pricing$|forgot-password$|reset-password$|locale$|storage$).+')
    ->name('frontend.page');
