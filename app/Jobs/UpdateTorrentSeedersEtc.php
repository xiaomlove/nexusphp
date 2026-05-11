<?php

namespace App\Jobs;

use App\Events\TorrentPeersUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateTorrentSeedersEtc implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginTorrentId;

    private int $endTorrentId;

    private string $requestId;

    private ?string $idStr = null;

    private string $idRedisKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginTorrentId, int $endTorrentId, string $idStr, string $idRedisKey, string $requestId = '')
    {
        $this->beginTorrentId = $beginTorrentId;
        $this->endTorrentId = $endTorrentId;
        $this->idStr = $idStr;
        $this->idRedisKey = $idRedisKey;
        $this->requestId = $requestId;
    }

    public $tries = 1;

    public $timeout = 1800;

    /**
     * 获取任务时，应该通过的中间件。
     *
     * @return array
     */
    public function middleware()
    {
        return [new WithoutOverlapping($this->idRedisKey)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf(
            '[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC_HANDLE_JOB], commonRequestId: %s, beginTorrentId: %s, endTorrentId: %s, idStr: %s, idRedisKey: %s',
            $this->requestId, $this->beginTorrentId, $this->endTorrentId, $this->idStr, $this->idRedisKey
        );
        do_log("$logPrefix, job start ...");

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && ! empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = NexusDB::cache_get($this->idRedisKey);
        }
        if (empty($idStr)) {
            do_log("$logPrefix, no idStr or idRedisKey", 'error');

            return;
        }
        $torrentIdArr = explode(',', $idStr);
        // 批量取，简单化
        $torrents = [];
        $res = NexusDB::table('peers')
            ->selectRaw('torrent, seeder, COUNT(*) AS c')
            ->whereRaw("torrent in ($idStr)")
            ->groupBy(['torrent', 'seeder'])
            ->get();
        if ($res->isEmpty()) {
            do_log("$logPrefix, no data from idStr: $idStr", 'error');

            return;
        }
        foreach ($res as $row) {
            if ($row->seeder == 'yes') {
                $key = 'seeders';
            } else {
                $key = 'leechers';
            }
            $torrents[$row->torrent][$key] = $row->c;
        }

        $res = NexusDB::table('comments')
            ->selectRaw('torrent, COUNT(*) AS c')
            ->whereRaw("torrent in ($idStr)")
            ->groupBy(['torrent'])
            ->get();
        foreach ($res as $row) {
            $torrents[$row->torrent]['comments'] = $row->c;
        }
        $seedersUpdates = $leechersUpdates = $commentsUpdates = [];
        foreach ($torrentIdArr as $id) {
            $seedersUpdates[] = sprintf('when %d then %d', $id, $torrents[$id]['seeders'] ?? 0);
            $leechersUpdates[] = sprintf('when %d then %d', $id, $torrents[$id]['leechers'] ?? 0);
            $commentsUpdates[] = sprintf('when %d then %d', $id, $torrents[$id]['comments'] ?? 0);
        }
        $sql = sprintf(
            'update torrents set seeders = case id %s end, leechers = case id %s end, comments = case id %s end where id in (%s)',
            implode(' ', $seedersUpdates), implode(' ', $leechersUpdates), implode(' ', $commentsUpdates), $idStr
        );
        $result = NexusDB::statement($sql);
        if ($delIdRedisKey) {
            NexusDB::cache_del($this->idRedisKey);
        }

        // Broadcast per-torrent peer count refresh (Reverb). Best-effort —
        // failures must not block the cleanup job, which is the source of
        // truth for these counters.
        foreach ($torrentIdArr as $id) {
            try {
                $torrentId = (int) $id;
                if ($torrentId <= 0) {
                    continue;
                }
                $seeders = (int) ($torrents[$id]['seeders'] ?? 0);
                $leechers = (int) ($torrents[$id]['leechers'] ?? 0);
                $comments = (int) ($torrents[$id]['comments'] ?? 0);
                event(new TorrentPeersUpdated(
                    torrent_id: $torrentId,
                    seeders: $seeders,
                    leechers: $leechers,
                    comments: $comments,
                    html: '',
                ));
            } catch (\Throwable $e) {
                do_log('TorrentPeersUpdated broadcast failed: '.$e->getMessage(), 'error');
            }
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update torrent count: %s, result: %s, cost time: %s seconds",
            count($torrentIdArr), var_export($result, true), $costTime
        ));
        do_log("$logPrefix, sql: $sql", 'debug');
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log('failed: '.$exception->getMessage().$exception->getTraceAsString(), 'error');
    }
}
