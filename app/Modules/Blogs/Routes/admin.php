<?php

use App\Modules\Blogs\Http\Controllers\Admin\BlogCategoriesController;
use App\Modules\Blogs\Http\Controllers\Admin\BlogsController;
use Illuminate\Support\Facades\Route;

Route::post('blog-categories/bulk-delete', [BlogCategoriesController::class, 'bulkDelete'])->name('blog-categories.bulk-delete');
Route::resource('blog-categories', BlogCategoriesController::class)
    ->parameters(['blog-categories' => 'blogCategory'])
    ->except(['show', 'create', 'edit']);
Route::post('blog-categories/{blogCategory}/toggle-status', [BlogCategoriesController::class, 'toggleStatus'])->name('blog-categories.toggle-status');

Route::post('blogs/bulk-delete', [BlogsController::class, 'bulkDelete'])->name('blogs.bulk-delete');
Route::resource('blogs', BlogsController::class)
    ->parameters(['blogs' => 'blog'])
    ->except(['show']);
Route::post('blogs/{blog}/toggle-status', [BlogsController::class, 'toggleStatus'])->name('blogs.toggle-status');
