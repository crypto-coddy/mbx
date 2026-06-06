<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trade extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'asset_id',
        'type',
        'amount',
        'quantity',
        'price_at_trade',
        'price_source',
        'closing_price',
        'profit_loss',
        'profit_loss_percent',
        'status',
        'closed_at',
        'settlement_requested_at',
        'settled_by',
        'admin_settlement_note',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'quantity' => 'decimal:8',
            'price_at_trade' => 'decimal:8',
            'closing_price' => 'decimal:8',
            'profit_loss' => 'decimal:8',
            'profit_loss_percent' => 'decimal:4',
            'closed_at' => 'datetime',
            'settlement_requested_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function referralCommissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'referenceable');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isPendingSettlement(): bool
    {
        return $this->status === 'pending_settlement';
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
