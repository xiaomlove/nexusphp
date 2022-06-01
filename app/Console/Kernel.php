<?php

namespace App\Console;

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
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('exam:assign_cronjob')->everyMinute()->withoutOverlapping();
        $schedule->command('exam:checkout_cronjob')->everyMinute()->withoutOverlapping();
        $schedule->command('exam:update_progress --bulk=1')->hourly()->withoutOverlapping();
        $schedule->command('backup:cronjob')->everyMinute()->withoutOverlapping();
        $schedule->command('hr:update_status')->everyMinute()->withoutOverlapping();
        $schedule->command('hr:update_status --ignore_time=1')->hourly()->withoutOverlapping();
        $schedule->command('user:delete_expired_token')->dailyAt('04:00');
        $schedule->command('claim:settle')->hourly()->when(function () {
            return Carbon::now()->format('d') == '01';
        })->withoutOverlapping();
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
