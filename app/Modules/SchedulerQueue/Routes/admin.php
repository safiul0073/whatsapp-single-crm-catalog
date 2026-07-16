<?php

use App\Modules\SchedulerQueue\Http\Controllers\Admin\SchedulerQueueController;
use Illuminate\Support\Facades\Route;

Route::get('scheduler-queues', [SchedulerQueueController::class, 'index'])->name('scheduler-queues.index');
Route::put('scheduler-queues/{schedulerEntry}', [SchedulerQueueController::class, 'update'])->name('scheduler-queues.update');
Route::post('scheduler-queues/{schedulerEntry}/run', [SchedulerQueueController::class, 'run'])->name('scheduler-queues.run');
Route::post('scheduler-queues/failed/{id}/retry', [SchedulerQueueController::class, 'retry'])->name('scheduler-queues.failed.retry');
Route::post('scheduler-queues/failed/retry-all', [SchedulerQueueController::class, 'retryAll'])->name('scheduler-queues.failed.retry-all');
Route::post('scheduler-queues/failed/{id}/forget', [SchedulerQueueController::class, 'forget'])->name('scheduler-queues.failed.forget');
Route::post('scheduler-queues/failed/flush', [SchedulerQueueController::class, 'flush'])->name('scheduler-queues.failed.flush');
Route::post('scheduler-queues/pending/clear', [SchedulerQueueController::class, 'clear'])->name('scheduler-queues.pending.clear');
Route::post('scheduler-queues/workers/restart', [SchedulerQueueController::class, 'restart'])->name('scheduler-queues.workers.restart');
