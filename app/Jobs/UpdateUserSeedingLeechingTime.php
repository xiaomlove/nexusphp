<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Repositories\CleanupRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateUserSeedingLeechingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $requestId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
        $this->requestId = $requestId;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds(Setting::get('main.autoclean_interval_four'));
    }

    public $tries = 1;

    public $timeout = 3600;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CleanupRepository::runBatchJob(CleanupRepository::USER_SEEDING_LEECHING_TIME_BATCH_KEY, $this->requestId);
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log("failed: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
    }
}
