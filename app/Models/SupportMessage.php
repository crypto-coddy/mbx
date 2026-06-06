<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use Auditable;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachments',
        'is_admin_reply',
        'read_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_admin_reply' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
