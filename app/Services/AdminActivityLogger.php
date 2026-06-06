<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminActivityLogger
{
    public function log(
        int $adminId,
        string $action,
        string $description,
        ?array $before = null,
        ?array $after = null,
        ?Model $target = null,
        ?Request $request = null,
    ): AdminActivityLog {
        return AdminActivityLog::create([
            'admin_id' => $adminId,
            'action' => $action,
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'target_type' => $target ? $target->getMorphClass() : null,
            'target_id' => $target?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
