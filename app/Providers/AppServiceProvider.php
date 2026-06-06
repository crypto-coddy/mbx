<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
                $prefix = trim($basePath, '/');

                config([
                    'livewire.asset_url' => rtrim($url, '/').'/livewire/livewire.js',
                ]);

                Livewire::setUpdateRoute(function ($handle) use ($prefix) {
                    return Route::post("{$prefix}/livewire/update", $handle)
                        ->middleware('web');
                });
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
