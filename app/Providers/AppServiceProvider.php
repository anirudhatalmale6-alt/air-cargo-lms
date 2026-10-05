<?php

namespace App\Providers;

use App\Models\Shipment;
use App\Policies\ShipmentPolicy;
use App\Services\Tracking\TrackingManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TrackingManager::class, fn ($app) => new TrackingManager(
            $app,
            config('tracking')
        ));
    }

    public function boot(): void
    {
        Gate::policy(Shipment::class, ShipmentPolicy::class);

        // Only admins reach the airline, AWB stock, charge type and user screens.
        Gate::define('administer', fn ($user) => $user->isAdmin());

        // Operations work: create and edit shipments, post status, upload documents.
        Gate::define('operate', fn ($user) => $user->isStaff());

        if ($this->app->environment('production') && config('app.force_https', true)) {
            URL::forceScheme('https');
        }
    }
}
