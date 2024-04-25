<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeductUserBonusWhenTorrentDeleted
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
        /**
         * Just a test
         */
        $torrent = $event->model;
        do_log(sprintf("torrent: %d is deleted, and it's pieces_hash is: %s", $torrent->id, $torrent->pieces_hash));
    }
}
