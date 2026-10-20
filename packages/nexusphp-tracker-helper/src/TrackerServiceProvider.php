<?php
namespace NexusPlugin\Tracker;

use Filament\Panel;
use NexusPlugin\Tracker\Filament\Pages\TrackerApi;
use NexusPlugin\Tracker\Filament\TrackerResource\Pages\TrackerStatus;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;

class TrackerServiceProvider extends PackageServiceProvider
{

    public function configurePackage(Package $package): void
    {
        $package->name(TrackerRepository::ID)
            ->hasTranslations()
            ->hasViews(TrackerRepository::ID)
        ;
    }

    public function packageRegistered()
    {
        Panel::configureUsing(function (Panel $panel) {
            $panel->plugin(Tracker::make())
                ->pages([
                    TrackerStatus::class,
                    TrackerApi::class,
                ]);
        });

    }

    public function packageBooted()
    {
        $basePath = dirname(__DIR__);

        $this->loadRoutesFrom($basePath . '/routes/web.php');

        if ($this->app->runningInConsole()) {

            // Schedule the command if we are using the application via the CLI
            $this->app->booted(function () {
                /**
                 * @var \Illuminate\Console\Scheduling\Schedule  $schedule
                 */
                $schedule = $this->app->make(Schedule::class);
                $schedule->call(function() {
                        TrackerRepository::getInstance()->checkStatus();
                    })
                    ->everyMinute()
                    ->name("go_tracker_check_status")
                    ->onOneServer()
                    ->withoutOverlapping()
                ;

            });
        }

    }

}
