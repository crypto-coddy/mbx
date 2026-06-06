<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'document_type',
        'file_path',
        'file_url',
        'mime_type',
        'file_size',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
