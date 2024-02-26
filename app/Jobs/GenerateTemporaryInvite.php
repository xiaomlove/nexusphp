<?php

namespace App\Jobs;

use App\Models\Invite;
use App\Repositories\ToolRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class GenerateTemporaryInvite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $count;

    private string $idRedisKey;

    private int $days;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $idRedisKey, int $days, int $count)
    {
        $this->idRedisKey = $idRedisKey;
        $this->days = $days;
        $this->count = $count;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addHours(1);
    }

    public $tries = 1;

    public $timeout = 1800;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $toolRep = new ToolRepository();
        $idStr = NexusDB::cache_get($this->idRedisKey);
        if (empty($idStr)) {
            do_log("no idStr of idRedisKey: {$this->idRedisKey}...");
            return;
        }
        $idArr = explode(",", $idStr);
        foreach ($idArr as $uid) {
            try {
                $hashArr = $toolRep->generateUniqueInviteHash([], $this->count, $this->count);
                $data = [];
                foreach($hashArr as $hash) {
                    $data[] = [
                        'inviter' => $uid,
                        'invitee' => '',
                        'hash' => $hash,
                        'valid' => 0,
                        'expired_at' => Carbon::now()->addDays($this->days),
                        'created_at' => Carbon::now(),
                    ];
                }
                if (!empty($data)) {
                    Invite::query()->insert($data);
                }
                do_log("success add $this->count temporary invite ($this->days days) to $uid");
            } catch (\Exception $exception) {
                do_log("fail add $this->count temporary invite ($this->days days) to $uid: " . $exception->getMessage(), 'error');
            }
        }

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
