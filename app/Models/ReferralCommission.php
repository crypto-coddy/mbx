<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    use Auditable;

    protected $fillable = [
        'beneficiary_user_id',
        'source_user_id',
        'commission_source',
        'trade_id',
        'referral_level',
        'trade_amount',
        'commission_rate',
        'commission_amount',
        'status',
        'credited_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'trade_amount' => 'decimal:8',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:8',
            'credited_at' => 'datetime',
        ];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function isSignupBonus(): bool
    {
        return $this->commission_source === 'signup';
    }
}
