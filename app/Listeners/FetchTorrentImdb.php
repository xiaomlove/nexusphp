<?php

namespace App\Listeners;

use App\Jobs\FetchImdbCacheJob;
use Nexus\Database\NexusDB;
use Nexus\Imdb\Imdb;

class FetchTorrentImdb
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
        if (!$torrentId) {
            do_log("FetchTorrentImdb: missing torrent id");
            return;
        }
        $imdbId = parse_imdb_id($event->model->url ?? '');
        if ($imdbId) {
            $lockKey = Imdb::getFetchQueueLockKey($imdbId);
            if (NexusDB::cache_get($lockKey)) {
                do_log("FetchImdbCacheJob already queued for torrent: $torrentId, imdb: $imdbId");
                return;
            }
            NexusDB::cache_put($lockKey, 1, 600);
        }
        FetchImdbCacheJob::dispatch($torrentId, $imdbId ? (string) $imdbId : null);
        do_log("FetchImdbCacheJob dispatched for torrent: $torrentId, imdb: $imdbId");
    }
}
