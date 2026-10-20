<?php

namespace App\Console;

use App\Jobs\CheckCleanup;
use App\Jobs\CheckQueueFailedJobs;
use App\Jobs\MaintainPluginState;
use App\Jobs\ManagePlugin;
use App\Jobs\RemoveUserDonorStatus;
use App\Jobs\RemoveUserVipStatus;
use App\Jobs\RemoveUserWarning;
use App\Jobs\SaveIpLogCacheToDB;
use App\Jobs\UpdateIsSeedBoxFromUserRecordsCache;
use App\Utils\ThirdPartyJob;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('cache:prune-stale-tags')->hourly();
        $schedule->call(static fn (): int => \Illuminate\Support\Facades\Artisan::call('exam:assign_cronjob'))
            ->name('exam:assign_cronjob')
            ->everyFiveMinutes();
        $schedule->command('exam:checkout_cronjob')->everyFiveMinutes();
        $schedule->command('exam:update_progress --bulk=1')->hourly();
        $schedule->call(static fn (): int => \Illuminate\Support\Facades\Artisan::call('backup:cronjob'))
            ->name('backup:cronjob')
            ->everyFiveMinutes();
        $schedule->command('hr:update_status')->everyTenMinutes();
        $schedule->command('torrent:seed_guard')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('torrent:official_seed_activity')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('torrent:official_seed_activity_magic')->hourly()->withoutOverlapping();
        $schedule->command('hr:update_status --ignore_time=1')->hourly();
        $schedule->command('user:delete_expired_token')->dailyAt('04:00');
        $schedule->command('claim:settle')->hourly()->when(function () {
            return Carbon::now()->format('d') == '01';
        });
        $schedule->command('meilisearch:import')->weeklyOn(1, "03:00");
        $schedule->command('torrent:load_pieces_hash')->dailyAt("01:00");
        $schedule->job(new CheckQueueFailedJobs())->everySixHours();
        $schedule->job(new MaintainPluginState())->everyFiveMinutes();
        $schedule->job(new UpdateIsSeedBoxFromUserRecordsCache())->everySixHours();
        $schedule->job(new CheckCleanup())->everyFifteenMinutes();
        $schedule->job(new SaveIpLogCacheToDB())->hourly();
        $schedule->job(new RemoveUserWarning())->everyFiveMinutes();
        $schedule->job(new RemoveUserVipStatus())->everyFiveMinutes();
        $schedule->job(new RemoveUserDonorStatus())->everyFiveMinutes();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

}
