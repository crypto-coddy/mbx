<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production', 'staging')) {
            DB::prohibitDestructiveCommands();
        }

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });
    }
}
