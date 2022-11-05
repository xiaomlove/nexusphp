<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateTorrentSeedersEtc implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginTorrentId;

    private int $endTorrentId;

    private string $requestId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginTorrentId, int $endTorrentId, string $requestId = '')
    {
        $this->beginTorrentId = $beginTorrentId;
        $this->endTorrentId = $endTorrentId;
        $this->requestId = $requestId;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds(Setting::get('main.autoclean_interval_three'));
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC], commonRequestId: %s, beginTorrentId: %s, endTorrentId: %s", $this->requestId, $this->beginTorrentId, $this->endTorrentId);
        $sql = sprintf("update torrents set seeders = (select count(*) from peers where torrent = torrents.id and seeder = 'yes'), leechers = (select count(*) from peers where torrent = torrents.id and seeder = 'no'), comments = (select count(*) from comments where torrent = torrents.id) where id > %s and id <= %s",
            $this->beginTorrentId, $this->endTorrentId
        );
        $result = NexusDB::statement($sql);
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], sql: %s, result: %s, cost time: %s seconds",
            preg_replace('/[\r\n\t]+/', ' ', $sql), var_export($result, true), $costTime
        ));
    }
}
