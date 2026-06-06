<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'symbol',
        'category',
        'display_name',
        'icon_url',
        'currency',
        'live_price',
        'price_change_24h',
        'chart_trend',
        'price_updated_at',
        'admin_price',
        'admin_override_active',
        'override_set_by',
        'override_set_at',
        'is_active',
        'trading_enabled',
        'min_trade_amount',
        'max_trade_amount',
        'api_config',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'live_price' => 'decimal:8',
            'price_change_24h' => 'decimal:4',
            'admin_price' => 'decimal:8',
            'admin_override_active' => 'boolean',
            'is_active' => 'boolean',
            'trading_enabled' => 'boolean',
            'min_trade_amount' => 'decimal:8',
            'max_trade_amount' => 'decimal:8',
            'api_config' => 'array',
            'price_updated_at' => 'datetime',
            'override_set_at' => 'datetime',
        ];
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function overrideSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_set_by');
    }

    public function effectivePrice(): string
    {
        if ($this->admin_override_active && $this->admin_price !== null) {
            return (string) $this->admin_price;
        }

        return (string) $this->live_price;
    }

    public function priceSource(): string
    {
        return ($this->admin_override_active && $this->admin_price !== null)
            ? 'admin_override'
            : 'live_api';
    }
}
