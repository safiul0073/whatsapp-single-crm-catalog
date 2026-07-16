<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Support\Facades\Auth;

trait HasCreatedUpdatedBy
{
    public static function bootHasCreatedUpdatedBy(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                if ($model->isFillable('created_by')) {
                    $model->created_by = Auth::id();
                }
                if ($model->isFillable('updated_by')) {
                    $model->updated_by = Auth::id();
                }
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && $model->isFillable('updated_by')) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
