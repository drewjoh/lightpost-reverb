<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewPulse', function ($user = null) {
            $allowed = config('pulse.allowed_ips', []);

            Log::info('Pulse IP check', [
                'ip' => request()->ip(),
                'allowed' => $allowed,
            ]);

            return in_array(request()->ip(), $allowed, true);
        });
    }
}
