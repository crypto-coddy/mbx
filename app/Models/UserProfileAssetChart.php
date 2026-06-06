<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileAssetChart extends Model
{
    use Auditable;

    protected $fillable = [
        'user_profile_id',
        'asset_id',
        'chart_trend',
        'chart_data',
        'set_by',
        'set_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'chart_data' => 'array',
            'set_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'user_profile_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
