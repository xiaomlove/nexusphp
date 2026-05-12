<?php

namespace App\Jobs;

use App\Enums\SeedBoxRecord\IpAsnEnum;
use App\Enums\SeedBoxRecord\IsAllowedEnum;
use App\Repositories\SeedBoxRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class UpdateIsSeedBoxFromUserRecordsCache implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * The 6h cadence walks every (IpAsn × IsAllowed) pair and rebuilds the
     * user/admin caches. `WithoutOverlapping` keeps a Horizon hot-restart
     * from spawning a second walker on top of the first one, which would
     * thrash the cache and (in the worst case) leave stale per-user entries
     * for users that were processed between the two runs.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('update-is-seedbox-from-user-records-cache'))->expireAfter(3600)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $rep = new SeedBoxRepository;
        foreach (IpAsnEnum::cases() as $field) {
            foreach (IsAllowedEnum::cases() as $isAllowed) {
                $rep->updateUserCacheCronjob($isAllowed, $field);
                do_log("SeedBoxRepository::updateUserCacheCronjob isAllowed: $isAllowed->name, field: $field->name success");
                $rep->updateAdminCacheCronjob($isAllowed, $field);
                do_log("SeedBoxRepository::updateAdminCacheCronjob isAllowed: $isAllowed->name, field: $field->name success");
            }
        }
        do_log('UpdateIsSeedBoxFromUserRecordsCache done!');
    }
}
