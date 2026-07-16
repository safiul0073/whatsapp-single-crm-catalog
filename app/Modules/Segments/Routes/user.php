<?php

use Illuminate\Support\Facades\Route;

Route::redirect('segments', 'groups')->name('segments.index');
Route::redirect('segments/{segment}', 'groups')->name('segments.preview');
Route::redirect('segments/{segment}/duplicate', 'groups')->name('segments.duplicate');
