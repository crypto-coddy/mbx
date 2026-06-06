<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'data',
        'type',
        'fcm_token',
        'status',
        'error_message',
        'is_broadcast',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_broadcast' => 'boolean',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
