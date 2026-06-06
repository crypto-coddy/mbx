<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'balance',
        'locked_balance',
        'reward_balance',
        'recharged_balance',
        'withdrawal_locked',
        'total_deposited',
        'total_withdrawn',
        'total_income',
        'total_commission',
        'total_profit',
        'total_loss',
        'currency',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:8',
            'locked_balance' => 'decimal:8',
            'reward_balance' => 'decimal:8',
            'recharged_balance' => 'decimal:8',
            'withdrawal_locked' => 'decimal:8',
            'total_deposited' => 'decimal:8',
            'total_withdrawn' => 'decimal:8',
            'total_income' => 'decimal:8',
            'total_commission' => 'decimal:8',
            'total_profit' => 'decimal:8',
            'total_loss' => 'decimal:8',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Balance available for trading (reward + recharged, minus locks). */
    public function availableBalance(): string
    {
        return bcsub((string) $this->balance, (string) $this->locked_balance, 8);
    }

    /** Admin-recharged funds the user may withdraw (profits on reward-only trades excluded). */
    public function withdrawableBalance(): string
    {
        $available = bcsub((string) $this->recharged_balance, (string) $this->withdrawal_locked, 8);

        return bccomp($available, '0', 8) > 0 ? $available : '0.00000000';
    }
}
