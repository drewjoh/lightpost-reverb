<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
            $allowed = array_filter(array_map(
                'trim',
                explode(',', (string) env('PULSE_ALLOWED_IPS', ''))
            ));

            return in_array(request()->ip(), $allowed, true);
        });
    }
}
