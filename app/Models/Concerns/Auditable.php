<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::creating(function (self $model): void {
            $userId = auth()->id();

            if ($userId === null) {
                return;
            }

            if ($model->isFillable('created_by') && empty($model->created_by)) {
                $model->created_by = $userId;
            }

            if ($model->isFillable('updated_by')) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function (self $model): void {
            $userId = auth()->id();

            if ($userId === null || ! $model->isFillable('updated_by')) {
                return;
            }

            $model->updated_by = $userId;
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
