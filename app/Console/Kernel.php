<?php

namespace App\Console;

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
        $schedule->command('exam:assign_cronjob')->everyMinute();
        $schedule->command('exam:checkout_cronjob')->everyMinute();
        $schedule->command('exam:update_progress --bulk=1')->hourly();
        $schedule->command('backup:cronjob')->everyMinute();
        $schedule->command('hr:update_status')->everyMinute();
        $schedule->command('hr:update_status --ignore_time=1')->hourly();
        $schedule->command('user:delete_expired_token')->dailyAt('04:00');
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
