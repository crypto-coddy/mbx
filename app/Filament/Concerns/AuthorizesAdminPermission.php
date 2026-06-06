<?php

namespace App\Filament\Concerns;

trait AuthorizesAdminPermission
{
    protected static function canAdmin(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }
}
