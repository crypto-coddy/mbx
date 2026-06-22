<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'avatar_path',
        'avatar_url',
        'date_of_birth',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'mobile_chart_data_source',
        'mobile_chart_data_version',
        'bank_name',
        'account_number',
        'account_holder_name',
        'ifsc_code',
        'account_type',
        'upi_id',
        'aadhaar_number',
        'pan_number',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'account_number' => 'encrypted',
            'aadhaar_number' => 'encrypted',
            'pan_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assetCharts(): HasMany
    {
        return $this->hasMany(UserProfileAssetChart::class);
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (?string $value) {
            if ($this->avatar_path) {
                return Storage::disk('public')->url($this->avatar_path);
            }

            return $value;
        });
    }
}
