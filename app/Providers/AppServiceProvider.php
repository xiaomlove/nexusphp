<?php

namespace App\Providers;

use App\Http\Controllers\Legacy\LegacyPageController;
use App\Legacy\LegacyChrome;
use App\Legacy\LegacyContext;
use Filament\Facades\Filament;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Nexus\Database\NexusDB;

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

        // Phase 1 of the legacy migration — see docs/legacy-strategy.md.
        // LegacyContext is the typed read-only API new Laravel code uses
        // when it needs to read legacy-domain state (current $CURUSER,
        // settings, ...).
        $this->app->singleton(LegacyContext::class);

        // The "wrap" half of the Phase 1 seam — runs a legacy
        // public/<page>.php through the Laravel pipeline. Constructor
        // injection picks up `LegacyContext` automatically; the
        // `legacyRoot` argument is the only piece the container can't
        // resolve on its own, so we bind it here once.
        $this->app->bind(LegacyPageController::class, fn ($app) => new LegacyPageController(
            $app->make(LegacyContext::class),
            base_path('public'),
        ));

        // Phase 1.3 of the legacy migration — see docs/legacy-strategy.md.
        // Renders the legacy site chrome (the HTML envelope produced by
        // `stdhead()` / `stdfoot()` in `include/functions.php`) as plain
        // strings, so modern controllers can wrap their output in the
        // legacy look-and-feel via `view('layouts.legacy', ...)` without
        // `require`-ing the legacy include chain themselves. Singleton
        // because the bootstrap step is intentionally one-shot per process.
        $this->app->singleton(LegacyChrome::class);

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
        $forceScheme = strtolower((string) config('app.force_scheme'));
        if (config('app.env') == 'production' && in_array($forceScheme, ['https', 'http'])) {
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
            Css::make('sprites', asset('styles/sprites.css')),
            Css::make('admin', asset('styles/admin.css')),
        ]);

        do_action('nexus_boot');
    }

    private function customScheduleTask(): void
    {
        if (! isRunningInConsole()) {
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
                if (getenv('RUNNING_IN_DOCKER') == '1' && $event->task->output === $event->task->getDefaultOutput()) {
                    $event->task->appendOutputTo('/proc/1/fd/1');
                }
            }
        );
    }
}
