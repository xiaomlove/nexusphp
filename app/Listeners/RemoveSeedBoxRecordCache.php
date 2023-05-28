<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Nexus\Database\NexusDB;

class RemoveSeedBoxRecordCache implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        NexusDB::cache_del_by_pattern("nexus_is_ip_seed_box:ip:*");
    }
}
