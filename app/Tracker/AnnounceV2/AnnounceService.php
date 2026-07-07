<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

use App\Models\Torrent;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\TorrentRepository;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\Bencode;
use Throwable;

final class AnnounceService
{
    public const SESSION_TTL = 2700;
    private const MIN_STATE_UPDATE_INTERVAL = 30;
    private const CLEANUP_LIMIT = 128;

    public function __construct(private readonly \Redis $redis)
    {
    }

    public function handle(AnnounceRequest $request): string
    {
        $user = $this->getUser($request->passkey);
        $GLOBALS['CURUSER'] = $user;
        $this->assertUserAllowed($user);

        try {
            $client = (new AgentAllowRepository())->checkClient($request->peerId, $request->agent);
        } catch (Throwable $e) {
            throw new AnnounceFailure($e->getMessage());
        }

        $torrent = $this->getTorrent($request->infoHash);
        $this->assertTorrentAllowed($torrent, $user, $request);
        $this->assertPaidTorrentAllowed($torrent, $user, $request);

        [$upMultiplier, $downMultiplier, $creditCap] = $this->accountingPolicy($torrent, $user, $request);
        $keyspace = Keyspace::keys((int) $torrent['id'], (int) $user['id'], $request->peerId);
        $keys = array_values(array_intersect_key($keyspace, array_flip([
            'session', 'data', 'active', 'seeders', 'leechers', 'meta', 'stream',
        ])));

        $peerData = implode(',', [
            $request->ipv4 === '' ? '' : bin2hex(inet_pton($request->ipv4)),
            $request->ipv6 === '' ? '' : bin2hex(inet_pton($request->ipv6)),
            $request->port,
        ]);

        $arguments = [
            $keyspace['member'], time(), self::SESSION_TTL, $request->event,
            $request->uploaded, $request->downloaded, $request->left, $peerData,
            $torrent['id'], $user['id'], bin2hex($request->peerId), $request->ip,
            $request->port, $request->agent, $request->numwant, self::CLEANUP_LIMIT,
            self::MIN_STATE_UPDATE_INTERVAL, $torrent['seeders'], $torrent['leechers'],
            $torrent['times_completed'], $upMultiplier, $downMultiplier, $creditCap,
        ];

        $result = $this->redis->eval(LuaScripts::announce(), [...$keys, ...$arguments], count($keys));
        if (!is_array($result) || count($result) < 5) {
            throw new AnnounceFailure('Tracker state unavailable', 60);
        }

        $response = [
            'interval' => $this->announceInterval($torrent),
            'min interval' => 300,
            'complete' => max(0, (int) $result[1]),
            'incomplete' => max(0, (int) $result[2]),
            'downloaded' => max(0, (int) $result[3]),
            'peers' => '',
            'peers6' => '',
        ];

        for ($i = 5, $count = count($result); $i < $count; $i++) {
            $this->appendPeer($response, (string) $result[$i]);
        }

        // Updating this preference is deliberately asynchronous in v2. It is
        // metadata, not part of the peer-state consistency boundary.
        if ((int) ($user['clientselect'] ?? 0) !== (int) $client->id) {
            $this->redis->hSet('av2:deferred:user-client', (string) $user['id'], (string) $client->id);
        }

        return Bencode::encode($response);
    }

    public static function failure(string $message, int $retryAfter = 300): string
    {
        return Bencode::encode([
            'failure reason' => $message,
            'interval' => $retryAfter,
            'min interval' => $retryAfter,
        ]);
    }

    private function getUser(string $passkey): array
    {
        global $Cache;

        $cacheKey = 'announce_v2:user:' . $passkey;
        $user = $Cache->get_value($cacheKey);
        if (is_array($user) && !empty($user['__invalid'])) {
            throw new AnnounceFailure('Invalid passkey', 3600);
        }
        if (is_array($user)) {
            return $user;
        }

        $statement = mysql_prepare(
            'SELECT id, username, downloadpos, enabled, uploaded, downloaded, class, parked, '
            . 'clientselect, showclienterror, passkey, donor, donoruntil, seedbonus, tracker_url_id '
            . 'FROM users WHERE passkey = :passkey LIMIT 1'
        );
        $statement->execute(['passkey' => $passkey]);
        $user = mysql_fetch_assoc($statement);
        if (!$user) {
            $Cache->cache_value($cacheKey, ['__invalid' => 1], 3600);
            throw new AnnounceFailure('Invalid passkey', 3600);
        }
        $Cache->cache_value($cacheKey, $user, 300);
        return $user;
    }

    private function getTorrent(string $infoHash): array
    {
        global $Cache;

        $cacheKey = 'announce_v2:torrent:' . sha1($infoHash);
        $torrent = $Cache->get_value($cacheKey);
        if (is_array($torrent) && !empty($torrent['__missing'])) {
            throw new AnnounceFailure('Torrent not registered with this tracker', 3600);
        }
        if (is_array($torrent)) {
            return $torrent;
        }

        $field = NexusDB::binaryField('info_hash');
        $statement = mysql_prepare(
            "SELECT torrents.id, torrents.size, torrents.owner, torrents.sp_state, torrents.seeders, "
            . "torrents.leechers, torrents.times_completed, torrents.added, torrents.banned, torrents.hr, "
            . "torrents.approval_status, torrents.price, torrents.promotion_time_type, "
            . "torrents.promotion_until, categories.mode "
            . "FROM torrents LEFT JOIN categories ON torrents.category = categories.id WHERE $field LIMIT 1"
        );
        $statement->execute(['info_hash' => NexusDB::binaryFieldBindValue($infoHash)]);
        $torrent = mysql_fetch_assoc($statement);
        if (!$torrent) {
            $Cache->cache_value($cacheKey, ['__missing' => 1], 3600);
            throw new AnnounceFailure('Torrent not registered with this tracker', 3600);
        }
        $Cache->cache_value($cacheKey, $torrent, 300);
        return $torrent;
    }

    private function assertUserAllowed(array $user): void
    {
        if ($user['enabled'] !== 'yes') {
            throw new AnnounceFailure('Your account is disabled', 1800);
        }
        if ($user['parked'] === 'yes') {
            throw new AnnounceFailure('Your account is parked', 1800);
        }
        if ($user['downloadpos'] === 'no') {
            throw new AnnounceFailure('Your downloading privileges have been disabled', 1800);
        }
    }

    private function assertTorrentAllowed(array $torrent, array $user, AnnounceRequest $request): void
    {
        if (bccomp($request->left, (string) $torrent['size'], 0) === 1) {
            throw new AnnounceFailure('Invalid bytes left');
        }
        if ($torrent['banned'] === 'yes' && !user_can('seebanned', false, (int) $user['id'])) {
            throw new AnnounceFailure('Torrent banned', 1800);
        }
        if ((int) $torrent['approval_status'] !== Torrent::APPROVAL_STATUS_ALLOW
            && get_setting('torrent.approval_status_none_visible') === 'no'
            && !user_can('seebanned', false, (int) $user['id'])) {
            throw new AnnounceFailure('Torrent review not approved', 1800);
        }
    }

    private function assertPaidTorrentAllowed(array $torrent, array $user, AnnounceRequest $request): void
    {
        if ($request->isSeeder() || (int) $torrent['price'] <= 0 || (int) $torrent['owner'] === (int) $user['id']
            || get_setting('torrent.paid_torrent_enabled') !== 'yes') {
            return;
        }

        $repository = new TorrentRepository();
        $status = $repository->getBuyStatus((int) $user['id'], (int) $torrent['id']);
        if ($status !== TorrentRepository::BUY_STATUS_SUCCESS) {
            if ($status === TorrentRepository::BUY_STATUS_UNKNOWN) {
                \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\BuyTorrent((int) $user['id'], (int) $torrent['id']));
            }
            throw new AnnounceFailure('Purchase in progress; please try again later', 300);
        }
    }

    private function accountingPolicy(array $torrent, array $user, AnnounceRequest $request): array
    {
        $promotion = apply_filter('torrent_promotion', $torrent);
        $normal = Torrent::PROMOTION_NORMAL;
        if (!empty($promotion['__ignore_global_sp_state'])) {
            $state = (int) $promotion['sp_state'];
        } else {
            $global = (int) get_global_sp_state();
            $state = $global !== $normal ? $global : (int) $torrent['sp_state'];
        }
        $policy = Torrent::$promotionTypes[$state] ?? Torrent::$promotionTypes[$normal];
        $up = (string) $policy['up_multiplier'];
        $down = (string) $policy['down_multiplier'];

        $uploaderMultiplier = (string) get_setting('torrent.uploaderdouble', 1);
        if ((int) $torrent['owner'] === (int) $user['id'] && (float) $uploaderMultiplier > (float) $up) {
            $up = $uploaderMultiplier;
        }
        if ((int) $user['class'] === (int) User::CLASS_VIP) {
            $down = '0';
        }

        $creditCap = '0';
        if (get_setting('seed_box.enabled') === 'yes'
            && (int) $torrent['owner'] !== (int) $user['id']
            && (int) $user['class'] < (int) User::CLASS_VIP
            && !is_donor($user)
            && isIPSeedBox($request->ip, (int) $user['id'])) {
            if (get_setting('seed_box.no_promotion') === 'yes') {
                $up = $down = '1';
            }
            $maxTimes = (string) get_setting('seed_box.max_uploaded', 0);
            if (bccomp($maxTimes, '0', 4) === 1) {
                $creditCap = bcmul((string) $torrent['size'], $maxTimes, 0);
            }
        }

        return [$up, $down, $creditCap];
    }

    private function announceInterval(array $torrent): int
    {
        $age = max(0, time() - strtotime((string) $torrent['added']));
        $interval = (int) get_setting('main.announce_interval', 1800);
        if ((int) get_setting('main.anninterthreeage', 0) > 0
            && $age >= (int) get_setting('main.anninterthreeage') * 86400) {
            $interval = (int) get_setting('main.anninterthree', $interval);
        } elseif ((int) get_setting('main.annintertwoage', 0) > 0
            && $age >= (int) get_setting('main.annintertwoage') * 86400) {
            $interval = (int) get_setting('main.annintertwo', $interval);
        }
        return max(300, $interval) + random_int(0, 60);
    }

    private function appendPeer(array &$response, string $encoded): void
    {
        $parts = explode(',', $encoded, 3);
        if (count($parts) !== 3) {
            return;
        }
        [$v4, $v6, $port] = $parts;
        $port = (int) $port;
        if ($v4 !== '' && strlen($v4) === 8) {
            $response['peers'] .= hex2bin($v4) . pack('n', $port);
        }
        if ($v6 !== '' && strlen($v6) === 32) {
            $response['peers6'] .= hex2bin($v6) . pack('n', $port);
        }
    }
}
