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

    public $tries = 1;

    public $timeout = 1800;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC_HANDLE_JOB], commonRequestId: %s, beginTorrentId: %s, endTorrentId: %s", $this->requestId, $this->beginTorrentId, $this->endTorrentId);
//        $sql = sprintf("update torrents set seeders = (select count(*) from peers where torrent = torrents.id and seeder = 'yes'), leechers = (select count(*) from peers where torrent = torrents.id and seeder = 'no'), comments = (select count(*) from comments where torrent = torrents.id) where id > %s and id <= %s",
//            $this->beginTorrentId, $this->endTorrentId
//        );
//        $result = NexusDB::statement($sql);

        $torrents = NexusDB::table('torrents')
            ->where('id', '>', $this->beginTorrentId)
            ->where('id', '<=', $this->endTorrentId)
            ->get(['id'])
        ;
        foreach ($torrents as $torrent) {
            $peerResult = NexusDB::table('peers')
                ->where('torrent', $torrent->id)
                ->selectRaw("count(*) as count, seeder")
                ->groupBy('seeder')
                ->get()
            ;
            $commentResult = NexusDB::table('comments')
                ->where('torrent', $torrent->id)
                ->selectRaw("count(*) as count")
                ->first()
            ;
            $update = [
                'comments' => $commentResult && $commentResult->count !== null ? $commentResult->count : 0,
                'seeders' => 0,
                'leechers' => 0,
            ];
            foreach ($peerResult as $item) {
                if ($item->seeder == 'yes') {
                    $update['seeders'] = $item->count;
                } elseif ($item->seeder == 'no') {
                    $update['leechers'] = $item->count;
                }
            }
            NexusDB::table('torrents')->where('id', $torrent->id)->update($update);
            do_log("[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC_HANDLE_TORRENT], [SUCCESS]: $torrent->id => " . json_encode($update));
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update torrent count: %s, cost time: %s seconds",
            count($torrents), $costTime
        ));
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
