<?php

namespace App\Providers;

use App\Http\Middleware\Locale;
use Carbon\Carbon;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Passport;
use Nexus\Database\NexusDB;
use Nexus\Nexus;
use Filament\Facades\Filament;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        do_action('nexus_register');

        // Telescope is only registered when explicitly enabled, since it
        // captures every request/query/job and is intended for local and
        // staging debugging — not production traffic.
        if ($this->app->environment('local', 'staging') || config('telescope.enabled')) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        global $plugin;
        $plugin->start();
        NexusDB::customModel();
        DB::connection(config('database.default'))->enableQueryLog();
        $forceScheme = strtolower(env('FORCE_SCHEME'));
        if (env('APP_ENV') == "production" && in_array($forceScheme, ['https', 'http'])) {
            URL::forceScheme($forceScheme);
        }
        $this->customScheduleTask();

        Filament::serving(function () {
            Filament::registerNavigationGroups([
                'User',
                'Torrent',
                'Tracker',
                'Role & Permission',
                'Other',
                'Section',
                'Oauth',
                'System',
            ]);
        });

        FilamentAsset::register([
            Css::make("sprites", asset('styles/sprites.css')),
            Css::make("admin", asset('styles/admin.css')),
        ]);

        do_action('nexus_boot');
    }

    private function customScheduleTask(): void
    {
        if (!isRunningInConsole()) {
            return;
        }
        /** @var Dispatcher $eventDispatcher */
        $eventDispatcher = $this->app->make(Dispatcher::class);

        $eventDispatcher->listen(
            events: [ScheduledTaskStarting::class],
            listener: static function (ScheduledTaskStarting $event): void {
                $event->task->onOneServer()->withoutOverlapping();
                // When we are using stterr as output for logs then schedule tasks will not output
                // any logs  due the /dev/null usage. Let's fix this by appending the output to
                // the docker process.
                if (getenv("RUNNING_IN_DOCKER") == "1" && $event->task->output === $event->task->getDefaultOutput()) {
                    $event->task->appendOutputTo("/proc/1/fd/1");
                }
            }
        );
    }
}
