<?php

namespace App\Console\Commands;

use App\Tracker\AnnounceV2\AnnounceService;
use App\Tracker\AnnounceV2\Keyspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class AnnounceV2Warmup extends Command
{
    protected $signature = 'tracker:announce-v2-warmup {--chunk=1000}';

    protected $description = 'Warm Redis announce-v2 swarm state from the current MySQL tracker state';

    public function handle(): int
    {
        $redis = NexusDB::redis();
        $chunk = max(100, (int) $this->option('chunk'));
        $cutoff = time() - AnnounceService::SESSION_TTL;
        $loaded = 0;

        DB::table('torrents')->select(['id', 'seeders', 'leechers', 'times_completed'])
            ->orderBy('id')->chunkById($chunk, function ($torrents) use ($redis) {
                $pipe = $redis->multi(\Redis::PIPELINE);
                foreach ($torrents as $torrent) {
                    $keys = Keyspace::keys((int) $torrent->id, 0, str_repeat("\0", 20));
                    $pipe->hMSet($keys['meta'], [
                        'seeders' => max(0, (int) $torrent->seeders),
                        'leechers' => max(0, (int) $torrent->leechers),
                        'completed' => max(0, (int) $torrent->times_completed),
                    ]);
                }
                $pipe->exec();
            });

        DB::table('peers')
            ->where('last_action', '>=', date('Y-m-d H:i:s', $cutoff))
            ->select([
                'id', 'torrent', 'userid', 'peer_id', 'ipv4', 'ipv6', 'port', 'uploaded',
                'downloaded', 'to_go', 'seeder', 'last_action',
            ])->orderBy('id')->chunkById($chunk, function ($peers) use ($redis, &$loaded) {
                $pipe = $redis->multi(\Redis::PIPELINE);
                foreach ($peers as $peer) {
                    $peerId = (string) $peer->peer_id;
                    if (strlen($peerId) !== 20) {
                        continue;
                    }
                    $keys = Keyspace::keys((int) $peer->torrent, (int) $peer->userid, $peerId);
                    $timestamp = max(1, strtotime((string) $peer->last_action));
                    $seeder = $peer->seeder === 'yes';
                    $peerData = implode(',', [
                        $this->packedHex((string) $peer->ipv4, FILTER_FLAG_IPV4),
                        $this->packedHex((string) $peer->ipv6, FILTER_FLAG_IPV6),
                        (int) $peer->port,
                    ]);
                    $pipe->hMSet($keys['session'], [
                        'uploaded' => (string) $peer->uploaded,
                        'downloaded' => (string) $peer->downloaded,
                        'left' => (string) $peer->to_go,
                        'seeder' => $seeder ? '1' : '0',
                        'last_seen' => $timestamp,
                    ]);
                    $pipe->expire($keys['session'], AnnounceService::SESSION_TTL + 60);
                    $pipe->hSet($keys['data'], $keys['member'], $peerData);
                    $pipe->zAdd($keys['active'], $timestamp, $keys['member']);
                    $pipe->zAdd($seeder ? $keys['seeders'] : $keys['leechers'], $timestamp, $keys['member']);
                    $loaded++;
                }
                $pipe->exec();
                $this->line("Loaded $loaded peers");
            }, 'id');

        $redis->set('announce_v2:warmed_at', (string) time());
        $this->info("announce-v2 warmup complete: $loaded active peers");
        return self::SUCCESS;
    }

    private function packedHex(string $ip, int $flag): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, $flag) === false) {
            return '';
        }
        return bin2hex(inet_pton($ip));
    }
}

