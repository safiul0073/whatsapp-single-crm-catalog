<?php

use App\Modules\Campaigns\Http\Controllers\User\CampaignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:campaigns.view'])->group(function () {
    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campaigns/{campaign}/report', [CampaignController::class, 'report'])->name('campaigns.report');
    Route::get('campaigns/{campaign}/preview-recipients', [CampaignController::class, 'previewRecipients'])->name('campaigns.preview-recipients');
    Route::get('campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('campaigns.export');
});

Route::middleware(['can:campaigns.create'])->group(function () {
    Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campaigns/doctor', [CampaignController::class, 'doctor'])->name('campaigns.doctor');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
});

Route::middleware(['can:campaigns.manage'])->group(function () {
    Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
    Route::post('campaigns/{campaign}/re-run', [CampaignController::class, 'reRun'])->name('campaigns.re-run');
});
