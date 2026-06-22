<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class DepositRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'note',
        'payment_screenshot_path',
        'payment_screenshot_url',
        'status',
        'rejection_reason',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    protected function paymentScreenshotUrl(): Attribute
    {
        return Attribute::get(function (?string $value) {
            if ($this->payment_screenshot_path) {
                return Storage::disk('public')->url($this->payment_screenshot_path);
            }

            return $value;
        });
    }
}
