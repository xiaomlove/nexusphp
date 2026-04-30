<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class NexusHorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::night();

        if ($slackWebhook = config('horizon.notifications.slack_webhook')) {
            Horizon::routeSlackNotificationsTo(
                $slackWebhook,
                config('horizon.notifications.slack_channel', '#horizon-alerts')
            );
        }
        if ($notifyEmail = config('horizon.notifications.mail')) {
            Horizon::routeMailNotificationsTo($notifyEmail);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * Access requires admin role (User::canAccessAdmin()), keeping it
     * consistent with the Filament admin panel and Telescope.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null) {
            return $user && $user->canAccessAdmin();
        });
    }
}
