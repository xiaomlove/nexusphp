<?php

namespace App\Jobs;

use App\Repositories\CleanupRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CheckQueueFailedJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Job middleware. `WithoutOverlapping` protects the queue worker from
     * processing two instances of this scheduled job concurrently — Horizon
     * hot-restarts or a slow run that overruns the 6h cadence would otherwise
     * walk the failed-jobs table twice and double up the admin notifications.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('check-queue-failed-jobs'))->expireAfter(600)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CleanupRepository::checkQueueFailedJobs();
        do_log('checkQueueFailedJobs run success.');
    }
}
