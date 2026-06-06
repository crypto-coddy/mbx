<?php

namespace App\Support;

use App\Models\User;

class PublicUserIdGenerator
{
    public static function generate(): string
    {
        $maxNumeric = User::withTrashed()
            ->whereNotNull('public_user_id')
            ->where('public_user_id', 'like', 'QX%')
            ->pluck('public_user_id')
            ->map(fn (string $id) => (int) substr($id, 2))
            ->max() ?? 0;

        do {
            $maxNumeric++;
            $id = 'QX'.str_pad((string) $maxNumeric, 8, '0', STR_PAD_LEFT);
        } while (User::withTrashed()->where('public_user_id', $id)->exists());

        return $id;
    }
}
