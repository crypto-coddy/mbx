<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DepositUpiId extends Model
{
    use Auditable;

    protected $fillable = [
        'label',
        'upi_id',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
