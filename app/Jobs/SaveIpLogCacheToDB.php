<?php

namespace App\Jobs;

use App\Repositories\IpLogRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SaveIpLogCacheToDB implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * `WithoutOverlapping` ensures two queue workers don't drain the same
     * Redis IP-log cache key concurrently, which would otherwise produce
     * duplicate `ip_log` rows (or lose entries to an interleaved `del`).
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('save-ip-log-cache-to-db'))->expireAfter(600)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        IpLogRepository::saveToDB();
        do_log('done');
    }
}
