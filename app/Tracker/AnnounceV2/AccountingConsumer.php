<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AccountingConsumer
{
    public const GROUP = 'mysql-accounting-v1';

    public function __construct(private readonly \Redis $redis)
    {
    }

    public function ensureGroups(): void
    {
        for ($shard = 0; $shard < Keyspace::SHARDS; $shard++) {
            try {
                $this->redis->xGroup('CREATE', Keyspace::stream($shard), self::GROUP, '0', true);
            } catch (\RedisException $e) {
                if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
                    throw $e;
                }
            }
        }
    }

    public function consume(string $consumer, int $count = 500, int $blockMs = 1000): int
    {
        $streams = [];
        for ($shard = 0; $shard < Keyspace::SHARDS; $shard++) {
            $streams[Keyspace::stream($shard)] = '>';
        }
        $messages = $this->redis->xReadGroup(self::GROUP, $consumer, $streams, $count, $blockMs);
        if (!$messages) {
            return 0;
        }

        $processed = 0;
        foreach ($messages as $stream => $entries) {
            $shard = $this->shardFromStream($stream);
            $this->persist($shard, $stream, $entries);
            $ids = array_keys($entries);
            if ($ids) {
                $this->redis->xAck($stream, self::GROUP, $ids);
                $processed += count($ids);
            }
        }
        return $processed;
    }

    public function recoverPending(string $consumer, int $minimumIdleMs = 60000, int $count = 500): int
    {
        $processed = 0;
        for ($shard = 0; $shard < Keyspace::SHARDS; $shard++) {
            $stream = Keyspace::stream($shard);
            $start = '0-0';
            do {
                $result = $this->redis->xAutoClaim(
                    $stream, self::GROUP, $consumer, $minimumIdleMs, $start, $count
                );
                $start = (string) ($result[0] ?? '0-0');
                $entries = $result[1] ?? [];
                if ($entries) {
                    $this->persist($shard, $stream, $entries);
                    $ids = array_keys($entries);
                    $this->redis->xAck($stream, self::GROUP, $ids);
                    $processed += count($ids);
                }
            } while ($entries && $start !== '0-0');
        }
        return $processed;
    }

    private function persist(int $shard, string $stream, array $entries): void
    {
        $batchToken = md5($stream . microtime(true) . random_bytes(8));

        DB::transaction(function () use ($shard, $entries, $batchToken) {
            $ledger = [];
            foreach ($entries as $id => $_) {
                $ledger[] = [
                    'stream' => $shard,
                    'event_id' => $id,
                    'batch_token' => $batchToken,
                    'processed' => false,
                ];
            }
            DB::table('announce_v2_events')->insertOrIgnore($ledger);
            $acceptedIds = DB::table('announce_v2_events')
                ->where('batch_token', $batchToken)
                ->where('processed', false)
                ->pluck('event_id')
                ->all();
            if (!$acceptedIds) {
                return;
            }

            $accepted = array_flip($acceptedIds);
            $users = [];
            $snatches = [];
            $torrents = [];
            $peers = [];
            $rawUploadedByPair = $this->loadRawUploaded($entries, $accepted);

            foreach ($entries as $eventId => $event) {
                if (!isset($accepted[$eventId])) {
                    continue;
                }
                $uid = (int) $event['user_id'];
                $torrentId = (int) $event['torrent_id'];
                $pair = $torrentId . ':' . $uid;
                $peerKey = $pair . ':' . $event['peer_hex'];

                $rawUpload = (string) $event['uploaded_delta'];
                $cap = (string) ($event['credit_cap'] ?? '0');
                if (bccomp($cap, '0', 0) === 1) {
                    $remaining = bcsub($cap, $rawUploadedByPair[$pair] ?? '0', 0);
                    if (bccomp($remaining, '0', 0) <= 0) {
                        $rawEligibleForCredit = '0';
                    } else {
                        $rawEligibleForCredit = bccomp($rawUpload, $remaining, 0) === 1 ? $remaining : $rawUpload;
                    }
                } else {
                    $rawEligibleForCredit = $rawUpload;
                }
                $creditedUp = $this->multiply($rawEligibleForCredit, (string) $event['up_multiplier']);
                $creditedDown = $this->multiply((string) $event['downloaded_delta'], (string) $event['down_multiplier']);
                $users[$uid]['uploaded'] = $this->add($users[$uid]['uploaded'] ?? '0', $creditedUp);
                $users[$uid]['downloaded'] = $this->add($users[$uid]['downloaded'] ?? '0', $creditedDown);
                $users[$uid]['last_announce_at'] = (int) $event['at'];

                $snatch = &$snatches[$pair];
                $snatch ??= [
                    'torrentid' => $torrentId,
                    'userid' => $uid,
                    'uploaded' => '0',
                    'downloaded' => '0',
                    'seedtime' => 0,
                    'leechtime' => 0,
                    'to_go' => (string) $event['left'],
                    'ip' => (string) $event['ip'],
                    'port' => (int) $event['port'],
                    'at' => (int) $event['at'],
                    'completed' => false,
                ];
                $snatch['uploaded'] = $this->add($snatch['uploaded'], (string) $event['uploaded_delta']);
                $snatch['downloaded'] = $this->add($snatch['downloaded'], (string) $event['downloaded_delta']);
                $timeField = $event['duration_was_seeder'] === '1' ? 'seedtime' : 'leechtime';
                $snatch[$timeField] += (int) $event['duration'];
                $snatch['to_go'] = (string) $event['left'];
                $snatch['ip'] = (string) $event['ip'];
                $snatch['port'] = (int) $event['port'];
                $snatch['at'] = (int) $event['at'];
                $snatch['completed'] = $snatch['completed'] || $event['completed'] === '1';
                $rawUploadedByPair[$pair] = $this->add($rawUploadedByPair[$pair] ?? '0', $rawUpload);
                unset($snatch);

                $torrents[$torrentId] = [
                    'seeders' => max(0, (int) $event['seeders']),
                    'leechers' => max(0, (int) $event['leechers']),
                    'at' => (int) $event['at'],
                    'completed' => ($torrents[$torrentId]['completed'] ?? 0) + (int) $event['completed'],
                ];

                $peers[$peerKey] = $event;
            }

            $this->persistUsers($users);
            $this->persistSnatches($snatches);
            $this->persistTorrents($torrents);
            $this->persistPeers($peers);

            DB::table('announce_v2_events')
                ->where('batch_token', $batchToken)
                ->update(['processed' => true]);
        }, 3);
    }

    private function persistUsers(array $users): void
    {
        foreach ($users as $uid => $values) {
            DB::table('users')->where('id', $uid)->update([
                'uploaded' => DB::raw('uploaded + ' . $values['uploaded']),
                'downloaded' => DB::raw('downloaded + ' . $values['downloaded']),
                'last_announce_at' => date('Y-m-d H:i:s', $values['last_announce_at']),
            ]);
        }
    }

    private function loadRawUploaded(array $entries, array $accepted): array
    {
        $pairs = [];
        foreach ($entries as $eventId => $event) {
            if (isset($accepted[$eventId])) {
                $pairs[(int) $event['torrent_id'] . ':' . (int) $event['user_id']] = [
                    (int) $event['torrent_id'], (int) $event['user_id'],
                ];
            }
        }
        if (!$pairs) {
            return [];
        }

        $query = DB::table('snatched')->select(['torrentid', 'userid', 'uploaded']);
        $query->where(function ($query) use ($pairs) {
            foreach ($pairs as [$torrentId, $userId]) {
                $query->orWhere(function ($query) use ($torrentId, $userId) {
                    $query->where('torrentid', $torrentId)->where('userid', $userId);
                });
            }
        });

        $result = [];
        foreach ($query->get() as $row) {
            $result[$row->torrentid . ':' . $row->userid] = (string) $row->uploaded;
        }
        return $result;
    }

    private function persistSnatches(array $snatches): void
    {
        foreach ($snatches as $value) {
            $now = date('Y-m-d H:i:s', $value['at']);
            $insert = [
                'torrentid' => $value['torrentid'], 'userid' => $value['userid'],
                'ip' => $value['ip'], 'port' => $value['port'],
                'uploaded' => 0, 'downloaded' => 0, 'to_go' => $value['to_go'],
                'seedtime' => 0, 'leechtime' => 0, 'startdat' => $now, 'last_action' => $now,
            ];
            DB::table('snatched')->insertOrIgnore($insert);
            $update = [
                'uploaded' => DB::raw('uploaded + ' . $value['uploaded']),
                'downloaded' => DB::raw('downloaded + ' . $value['downloaded']),
                'seedtime' => DB::raw('seedtime + ' . $value['seedtime']),
                'leechtime' => DB::raw('leechtime + ' . $value['leechtime']),
                'to_go' => $value['to_go'], 'ip' => $value['ip'], 'port' => $value['port'],
                'last_action' => $now,
            ];
            if ($value['completed']) {
                $update['finished'] = 'yes';
                $update['completedat'] = $now;
            }
            DB::table('snatched')
                ->where('torrentid', $value['torrentid'])
                ->where('userid', $value['userid'])
                ->update($update);
        }
    }

    private function persistTorrents(array $torrents): void
    {
        foreach ($torrents as $torrentId => $value) {
            DB::table('torrents')->where('id', $torrentId)->update([
                'seeders' => $value['seeders'],
                'leechers' => $value['leechers'],
                'times_completed' => DB::raw('times_completed + ' . $value['completed']),
                'visible' => 'yes',
                'last_action' => date('Y-m-d H:i:s', $value['at']),
            ]);
        }
    }

    private function persistPeers(array $peers): void
    {
        foreach ($peers as $event) {
            $identity = [
                'torrent' => (int) $event['torrent_id'],
                'userid' => (int) $event['user_id'],
                'peer_id' => hex2bin((string) $event['peer_hex']),
            ];
            if ($event['event'] === 'stopped') {
                DB::table('peers')->where($identity)->delete();
                continue;
            }
            [$v4, $v6] = array_pad(explode(',', (string) $event['peer_data'], 3), 3, '');
            $now = date('Y-m-d H:i:s', (int) $event['at']);
            DB::table('peers')->upsert([array_merge($identity, [
                'ip' => $event['ip'], 'ipv4' => $v4 === '' ? '' : inet_ntop(hex2bin($v4)),
                'ipv6' => $v6 === '' ? '' : inet_ntop(hex2bin($v6)),
                'port' => (int) $event['port'], 'uploaded' => $event['uploaded'],
                'downloaded' => $event['downloaded'], 'to_go' => $event['left'],
                'seeder' => $event['left'] === '0' ? 'yes' : 'no', 'agent' => $event['agent'],
                'last_action' => $now, 'started' => $now, 'passkey' => '',
            ])], ['torrent', 'peer_id', 'userid'], [
                'ip', 'ipv4', 'ipv6', 'port', 'uploaded', 'downloaded', 'to_go',
                'seeder', 'agent', 'last_action',
            ]);
        }
    }

    private function multiply(string $counter, string $multiplier): string
    {
        return bcadd(bcmul($counter, $multiplier, 6), '0', 0);
    }

    private function add(string $a, string $b): string
    {
        return bcadd($a, $b, 0);
    }

    private function shardFromStream(string $stream): int
    {
        if (!preg_match('/\{announce-v2:(\d+)}:accounting\z/', $stream, $matches)) {
            throw new RuntimeException("Invalid announce stream: $stream");
        }
        return (int) $matches[1];
    }
}
