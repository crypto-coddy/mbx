<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'bank_details',
        'status',
        'rejection_reason',
        'transaction_reference',
        'processed_by',
        'processed_at',
        'paid_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'bank_details' => 'array',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
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
