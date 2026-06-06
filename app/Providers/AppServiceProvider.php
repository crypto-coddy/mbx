<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($url = config('app.url')) {
            URL::forceRootUrl($url);

            $basePath = parse_url($url, PHP_URL_PATH);
            if ($basePath && $basePath !== '/') {
                config([
                    'livewire.asset_url' => rtrim($url, '/').'/livewire/livewire.js',
                ]);
            }
        }

        if ($this->app->environment('production', 'staging')) {
            URL::forceScheme('https');
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
