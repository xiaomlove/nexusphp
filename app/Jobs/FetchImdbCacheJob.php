<?php

namespace App\Jobs;

use App\Repositories\TorrentRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;
use Nexus\Imdb\Imdb;

class FetchImdbCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60];

    protected ?int $torrentId;
    protected string $imdbId;

    /**
     * Create a new job instance.
     *
     * @param int|null $torrentId
     * @param string|null $imdbId
     */
    public function __construct(?int $torrentId = null, ?string $imdbId = null)
    {
        $this->torrentId = $torrentId;
        $this->imdbId = (string) $imdbId;
    }

    /**
     * 获取作业标签 - Horizon中用于筛选
     */
    public function tags(): array
    {
        $tags = ['imdb_Job'];
        if ($this->torrentId) {
            $tags[] = 'torrent:' . $this->torrentId;
        }
        if ($this->imdbId !== '') {
            $tags[] = 'imdb:' . $this->imdbId;
        }
        return $tags;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->torrentId) {
                $torrentRep = new TorrentRepository();
                $torrentRep->fetchImdb($this->torrentId);
                do_log("FetchImdbCacheJob: 成功更新 Torrent ID: {$this->torrentId} 的 IMDb 信息");
                return;
            }
            if ($this->imdbId === '') {
                return;
            }
            $imdb = new Imdb();
            if ($imdb->getCacheStatus((int) $this->imdbId, false) == 1) {
                return;
            }
            $imdb->updateCache($this->imdbId);
            NexusDB::cache_del(Imdb::getMovieCoverCacheKey($this->imdbId));
            do_log("FetchImdbCacheJob: 成功更新 IMDb ID: {$this->imdbId} 的缓存");
        } finally {
            if ($this->imdbId !== '') {
                NexusDB::cache_del(Imdb::getFetchQueueLockKey($this->imdbId));
            }
        }
    }
}
