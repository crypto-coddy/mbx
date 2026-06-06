<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use Auditable;

    public $timestamps = true;

    protected $table = 'price_history';

    protected $fillable = [
        'asset_id',
        'price',
        'open',
        'high',
        'low',
        'close',
        'source',
        'interval',
        'segment_trend',
        'recorded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'open' => 'decimal:8',
            'high' => 'decimal:8',
            'low' => 'decimal:8',
            'close' => 'decimal:8',
            'recorded_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
