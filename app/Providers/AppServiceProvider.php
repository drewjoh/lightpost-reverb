<?php

namespace App\Providers;

use App\Pulse\Livewire\ReverbEventTypes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;

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
            return in_array(
                request()->ip(),
                config('pulse.allowed_ips', []),
                true
            );
        });

        $this->callAfterResolving('livewire', function (LivewireManager $livewire) {
            $livewire->component('reverb.event-types', ReverbEventTypes::class);
        });
    }
}
