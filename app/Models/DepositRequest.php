<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'note',
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
}
