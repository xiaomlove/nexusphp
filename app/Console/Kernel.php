<?php

namespace App\Console;

use App\Jobs\CheckCleanup;
use App\Jobs\CheckQueueFailedJobs;
use App\Jobs\MaintainPluginState;
use App\Jobs\RemoveUserDonorStatus;
use App\Jobs\RemoveUserVipStatus;
use App\Jobs\RemoveUserWarning;
use App\Jobs\SaveIpLogCacheToDB;
use App\Jobs\UpdateIsSeedBoxFromUserRecordsCache;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('cache:prune-stale-tags')->hourly();
        $schedule->command('exam:assign_cronjob')->everyMinute();
        $schedule->command('exam:checkout_cronjob')->everyFiveMinutes();
        $schedule->command('exam:update_progress --bulk=1')->hourly();
        $schedule->command('backup:cronjob')->everyMinute();
        // Phase 2.1.b: replaces the deleted public/cron.php. The
        // command wraps legacy `autoclean()`, which is itself
        // self-throttling via `avps.lastcleantime` —
        // `everyMinute()` is therefore safe even though the actual
        // cleanup workload only runs once per `$autoclean_interval_one`.
        $schedule->command('cron:autoclean')->everyMinute()->withoutOverlapping();
        $schedule->command('hr:update_status')->everyTenMinutes();
        $schedule->command('hr:update_status --ignore_time=1')->hourly();
        $schedule->command('user:delete_expired_token')->dailyAt('04:00');
        $schedule->command('claim:settle')->hourly()->when(function () {
            return Carbon::now()->format('d') == '01';
        });
        $schedule->command('meilisearch:import')->weeklyOn(1, '03:00');
        $schedule->command('torrent:load_pieces_hash')->dailyAt('01:00');
        // Scheduler-level `withoutOverlapping()` complements the queue-side
        // `WithoutOverlapping` middleware on the `ShouldQueue` jobs below:
        //   * For non-`ShouldQueue` jobs (RemoveUser{Warning,VipStatus,
        //     DonorStatus}, MaintainPluginState, CheckCleanup) the job
        //     handler runs synchronously inside `schedule:run`, so the
        //     scheduler lock is the only defence against a slow run
        //     overrunning the next cadence tick.
        //   * For `ShouldQueue` jobs (CheckQueueFailedJobs,
        //     UpdateIsSeedBoxFromUserRecordsCache, SaveIpLogCacheToDB)
        //     this prevents the scheduler from re-dispatching the same
        //     job while a previous dispatch is still in the queue but
        //     not yet picked up by a worker.
        $schedule->job(new CheckQueueFailedJobs)->everySixHours()->withoutOverlapping();
        $schedule->job(new MaintainPluginState)->everyMinute()->withoutOverlapping();
        $schedule->job(new UpdateIsSeedBoxFromUserRecordsCache)->everySixHours()->withoutOverlapping();
        $schedule->job(new CheckCleanup)->everyFifteenMinutes()->withoutOverlapping();
        $schedule->job(new SaveIpLogCacheToDB)->hourly()->withoutOverlapping();
        $schedule->job(new RemoveUserWarning)->everyTwentySeconds()->withoutOverlapping();
        $schedule->job(new RemoveUserVipStatus)->everyMinute()->withoutOverlapping();
        $schedule->job(new RemoveUserDonorStatus)->everyMinute()->withoutOverlapping();

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
