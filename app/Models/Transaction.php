<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'direction',
        'balance_before',
        'balance_after',
        'referenceable_type',
        'referenceable_id',
        'description',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'balance_before' => 'decimal:8',
            'balance_after' => 'decimal:8',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Hide duplicate deposit approval rows from user-facing wallet history. */
    public function scopeVisibleInWalletHistory($query)
    {
        return $query->where(function ($query) {
            $query->where('type', '!=', 'deposit_status')
                ->orWhere(function ($query) {
                    $query->where('type', 'deposit_status')
                        ->where(function ($query) {
                            $query->whereNull('meta->status')
                                ->orWhere('meta->status', '!=', 'approved');
                        });
                });
        });
    }
}
