<?php

namespace App\Listeners;

use App\Models\Torrent;
use App\Repositories\TorrentRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class FetchTorrentImdb implements ShouldQueue
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
        $torrentId = $event->model?->id ?? 0;
        $torrentRep = new TorrentRepository();
        $torrentRep->fetchImdb($torrentId);
        do_log("fetchImdb for torrent: $torrentId done!");
    }
}
