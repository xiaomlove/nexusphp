<?php
/**
 * Handle announce and scrape
 *
 * @link https://github.com/HDInnovations/UNIT3D-Community-Edition/blob/master/app/Http/Controllers/AnnounceController.php
 * @link https://github.com/Rhilip/RidPT/blob/master/application/Controllers/Tracker/AnnounceController.php
 */
namespace App\Repositories;

use App\Exceptions\ClientNotAllowedException;
use App\Models\Cheater;
use App\Models\HitAndRun;
use App\Models\Peer;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserBanLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Exceptions\TrackerException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Rhilip\Bencode\Bencode;

class TrackerRepository extends BaseRepository
{
    const MIN_ANNOUNCE_WAIT_SECOND = 300;

    const MAX_PEER_NUM_WANT = 50;

    const MUST_BE_CHEATER_SPEED = 1024 * 1024 * 1024; //1024 MB/s
    const MAY_BE_CHEATER_SPEED = 1024 * 1024 * 100; //100 MB/s

    const ANNOUNCE_FIRST = 0;
    const ANNOUNCE_DUAL = 1;
    const ANNOUNCE_DUPLICATE = 2;

    // Port Blacklist
    protected const BLACK_PORTS = [
        22,  // SSH Port
        53,  // DNS queries
        80, 81, 8080, 8081,  // Hyper Text Transfer Protocol (HTTP) - port used for web traffic
        411, 412, 413,  // 	Direct Connect Hub (unofficial)
        443,  // HTTPS / SSL - encrypted web traffic, also used for VPN tunnels over HTTPS.
        1214,  // Kazaa - peer-to-peer file sharing, some known vulnerabilities, and at least one worm (Benjamin) targeting it.
        3389,  // IANA registered for Microsoft WBT Server, used for Windows Remote Desktop and Remote Assistance connections
        4662,  // eDonkey 2000 P2P file sharing service. http://www.edonkey2000.com/
        6346, 6347,  // Gnutella (FrostWire, Limewire, Shareaza, etc.), BearShare file sharing app
        6699,  // Port used by p2p software, such as WinMX, Napster.
    ];

    public function announce(Request $request): \Illuminate\Http\Response
    {
        do_log("queryString: " . $request->getQueryString());
        /**
         * Note: In Octane this class will be reused, use variable is better !!!
         */
        $userUpdates = [];
        try {
            $withPeers = false;
            $queries = $this->checkAnnounceFields($request);
            $user = $this->checkUser($request);
            $clientAllow = $this->checkClient($request);
            $torrent = $this->checkTorrent($queries, $user);
            $isReAnnounce = $this->isReAnnounce($request);
            do_log("[IS_RE_ANNOUNCE] $isReAnnounce");
            if ($isReAnnounce < self::ANNOUNCE_DUPLICATE) {
                $isPeerExists = true;
                /** @var Peer $peerSelf */
                $peerSelf = Peer::query()
                    ->where('torrent', $torrent->id)
                    ->where('peer_id', $queries['peer_id'])
                    ->first();
                if (!$peerSelf) {
                    $isPeerExists = false;
                    $this->checkPeer($torrent, $queries, $user);
                    $this->checkPermission($torrent, $queries, $user);
                    $peerSelf = new Peer([
                        'torrent' => $torrent->id,
                        'peer_id' => $queries['peer_id'],
                        'userid' => $user->id,
                        'passkey' => $user->passkey,
                    ]);
                } elseif ($isReAnnounce == self::ANNOUNCE_FIRST) {
                    $this->checkMinInterval($peerSelf, $queries);
                }

                $snatch = Snatch::query()
                    ->where('torrentid', $torrent->id)
                    ->where('userid', $user->id)
                    ->orderBy('id', 'desc')
                    ->first();
                /**
                 * Note: Must get before update peer!
                 */
                $dataTraffic = $this->getDataTraffic($torrent, $queries, $user, $peerSelf, $snatch);

                /**
                 * Note: Only check in old session
                 */
                if ($peerSelf->exists) {
                    $this->checkCheater($torrent, $queries, $dataTraffic, $user, $peerSelf);
                    $this->checkSeedBox($torrent, $queries, $dataTraffic, $user, $peerSelf);
                }

                /**
                 * Note: Must update peer first, otherwise updateTorrent() count peer not correct
                 * Update: Will not change $peerSelf any more
                 */
                if ($isReAnnounce == self::ANNOUNCE_FIRST || ($isReAnnounce == self::ANNOUNCE_DUAL && $queries['event'] !== 'stopped')) {
                    $this->updatePeer($peerSelf, $queries, $isReAnnounce);
                }

                if ($isReAnnounce === self::ANNOUNCE_FIRST) {
                    $withPeers = true;
                    /**
                     * Note: Must update snatch first, otherwise peer `last_action` already change
                     */
                    $snatch = $this->updateSnatch($peerSelf, $queries, $dataTraffic, $snatch);
                    if ($queries['event'] == 'completed') {
                        $this->handleHitAndRun($user, $torrent, $snatch);
                    }

                    $this->updateTorrent($torrent, $queries, $isPeerExists);

                    if ($dataTraffic['uploaded_increment_for_user'] > 0) {
                        $userUpdates['uploaded'] = DB::raw('uploaded + ' . $dataTraffic['uploaded_increment_for_user']);
                    }
                    if ($dataTraffic['downloaded_increment_for_user'] > 0) {
                        $userUpdates['downloaded'] = DB::raw('downloaded + ' . $dataTraffic['downloaded_increment_for_user']);
                    }
                    if ($user->clientselect != $clientAllow->id) {
                        $userUpdates['clientselect'] = $clientAllow->id;
                    }
                    if ($user->showclienterror == 'yes') {
                        $userUpdates['showclienterror'] = 'no';
                    }
                }
            }
            $repDict = $this->generateSuccessAnnounceResponse($torrent, $queries, $user, $withPeers);
            if ($isReAnnounce == self::ANNOUNCE_FIRST) {
                do_action('announced', $torrent->toArray(), $user->toArray(), $queries);
            }
        } catch (ClientNotAllowedException $exception) {
            do_log("[ClientNotAllowedException] " . $exception->getMessage());
            if (isset($user) && $user->showclienterror == 'no') {
                $userUpdates['showclienterror'] = 'yes';
            }
            $repDict = $this->generateFailedAnnounceResponse($exception->getMessage());
        } catch (TrackerException $exception) {
            $repDict = $this->generateFailedAnnounceResponse($exception->getMessage());
        } catch (\Throwable $exception) {
            //other system exception
            do_log("[" . get_class($exception) . "] " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
            $repDict = $this->generateFailedAnnounceResponse("system error, report to sysop please, hint: " . nexus()->getRequestId());
        } finally {
            if (isset($user)) {
                $this->updateUser($user, $userUpdates);
            }
            return $this->sendFinalAnnounceResponse($repDict);
        }
    }

    /**
     * @param Request $request
     * @throws ClientNotAllowedException
     * @throws TrackerException
     * @refs
     */
    protected function checkClient(Request $request)
    {
        // Miss Header User-Agent is not allowed.
        if (! $request->header('User-Agent')) {
            throw new TrackerException('Invalid user-agent !');
        }

        // Block Other Browser, Crawler (May Cheater or Faker Client) by check Requests headers
        if ($request->header('accept-language') || $request->header('referer')
            || $request->header('accept-charset')

            /**
             * This header check may block Non-bittorrent client `Aria2` to access tracker,
             * Because they always add this header which other clients don't have.
             *
             * @see https://blog.rhilip.info/archives/1010/ ( in Chinese )
             */
            || $request->header('want-digest')
        ) {
            throw new TrackerException('Abnormal access blocked !');
        }

        $userAgent = $request->header('User-Agent');

        // Should also block User-Agent strings that are to long. (For Database reasons)
        if (\strlen((string) $userAgent) > 64) {
            throw new TrackerException('The User-Agent of this client is too long!');
        }

        // Block Browser by checking it's User-Agent
        if (\preg_match('/(Mozilla|Browser|Chrome|Safari|AppleWebKit|Opera|Links|Lynx|Bot|Unknown)/i', (string) $userAgent)) {
            throw new TrackerException('Browser, Crawler or Cheater is not Allowed.');
        }

        $agentAllowRep = new AgentAllowRepository();

        return $agentAllowRep->checkClient($request->peer_id, $userAgent, config('app.debug'));

    }

    protected function checkPasskey($passkey)
    {
        // If Passkey Lenght Is Wrong
        if (\strlen((string) $passkey) !== 32) {
            throw new TrackerException('Invalid passkey ! the length of passkey must be 32');
        }

        // If Passkey Format Is Wrong
        if (\strspn(\strtolower($passkey), 'abcdef0123456789') !== 32) {  // MD5 char limit
            throw new TrackerException("Invalid passkey ! The format of passkey is not correct");
        }

    }

    protected function checkAuthkey($authkey)
    {
        $arr = explode('|', $authkey);
        if (count($arr) != 3) {
            throw new TrackerException('Invalid authkey');
        }
        $torrentId = $arr[0];
        $uid = $arr[1];
        $torrentRep = new TorrentRepository();
        try {
            $decrypted = $torrentRep->checkTrackerReportAuthKey($authkey);
        } catch (\Exception $exception) {
            throw new TrackerException($exception->getMessage());
        }
        if (empty($decrypted)) {
            throw new TrackerException('Invalid authkey');
        }
        return compact('torrentId', 'uid');
    }

    /**
     * @param Request $request
     * @return array
     * @throws TrackerException
     */
    protected function checkAnnounceFields(Request $request): array
    {
        $queries = [];

        // Part.1 check Announce **Need** Fields
        foreach (['info_hash', 'peer_id', 'port', 'uploaded', 'downloaded', 'left'] as $item) {
            $itemData = $request->query->get($item);
            if (! \is_null($itemData)) {
                $queries[$item] = $itemData;
            } else {
                throw new TrackerException("key: $item is Missing !");
            }
        }

        foreach (['info_hash', 'peer_id'] as $item) {
            if (($length = \strlen((string) $queries[$item])) !== 20) {
                throw new TrackerException("Invalid $item ! $item is not 20 bytes long($length)");
            }
        }

        foreach (['uploaded', 'downloaded', 'left'] as $item) {
            $itemData = $queries[$item];
            if (! \is_numeric($itemData) || $itemData < 0) {
                throw new TrackerException("Invalid $item ! $item Must be a number greater than or equal to 0");
            }
        }

        // Part.2 check Announce **Option** Fields
        foreach (['event' => '', 'no_peer_id' => 1, 'compact' => 0, 'numwant' => 50, 'corrupt' => 0, 'key' => ''] as $item => $value) {
            $queries[$item] = $request->query->get($item, $value);
            if ($queries[$item] && $item == 'event') {
                $queries[$item] = strtolower($queries[$item]);
            }
        }

        foreach (['numwant', 'corrupt', 'no_peer_id', 'compact'] as $item) {
            if (! \is_numeric($queries[$item]) || $queries[$item] < 0) {
                throw new TrackerException("Invalid $item ! $item Must be a number greater than or equal to 0");
            }
        }

        if (! \in_array(\strtolower($queries['event']), ['started', 'completed', 'stopped', 'paused', ''])) {
            throw new TrackerException("Unsupported Event type {$queries['event']} .");
        }

        // Part.3 check Port is Valid and Allowed
        /**
         * Normally , the port must in 1 - 65535 , that is ( $port > 0 && $port < 0xffff )
         * However, in some case , When `&event=stopped` the port may set to 0.
         */
        if ($queries['port'] === 0 && \strtolower($queries['event']) !== 'stopped') {
            throw new TrackerException("Illegal port 0 under Event type {$queries['event']} .");
        }

        if (! \is_numeric($queries['port']) || $queries['port'] < 0 || $queries['port'] > 0xFFFF || \in_array($queries['port'], self::BLACK_PORTS,
                true)) {
            throw new TrackerException("Illegal port {$queries['port']} . Port should between 6881-64999");
        }

        // Part.4 Get User Ip Address
        $ip = nexus()->getRequestIp();
        $ipv4 = $ipv6 = '';
        $ipv4Temp = $request->query->get('ipv4', '');
        $ipv6Temp = $request->query->get('ipv6', '');
        //use the real ip first, ip from parameter second
        if (isIPV4($ip)) {
            $ipv4 = $ip;
        } elseif (isIPV4($ipv4Temp)) {
            $ipv4 = $ipv4Temp;
        }
        if (isIPV6($ip)) {
            $ipv6 = $ip;
        } elseif (isIPV6($ipv6Temp)) {
            $ipv6 = $ipv6Temp;
        }

        $queries['ip'] = $ip;
        $queries['ipv4'] = $ipv4;
        $queries['ipv6'] = $ipv6;

        // Part.5 Get Users Agent
        $queries['user_agent'] = $request->headers->get('user-agent');

        // Part.6 info_hash, binary
        $queries['info_hash'] = $queries['info_hash'];

        // Part.7
        $queries['peer_id'] = $queries['peer_id'];

        return $queries;
    }

    protected function checkUser(Request $request)
    {
        if ($authkey = $request->query->get('authkey')) {
            $checkResult = $this->checkAuthkey($authkey);
            $field = 'id';
            $value = $checkResult['uid'];
        } elseif ($passkey = $request->query->get('passkey')) {
            $this->checkPasskey($passkey);
            $field = 'passkey';
            $value = $passkey;
        } else {
            throw new TrackerException("Require authkey or passkey.");
        }
        /**
         * @var $user User
         */
       $user = User::query()->where($field, $value)->first();

        if (!$user) {
            throw new TrackerException("Invalid user $field: $value.");
        }
        try {
            $user->checkIsNormal();
        } catch (\Throwable $exception) {
            throw new TrackerException($exception->getMessage());
        }

        if ($user->parked == 'yes') {
            throw new TrackerException("Your account is parked! (Read the FAQ)");
        }
        if ($user->downloadpos == 'no') {
            throw new TrackerException("Your downloading privilege have been disabled! (Read the rules)");
        }

        return $user;
    }

    protected function checkTorrent($queries, User $user): Torrent
    {
        // Check Info Hash Against Torrents Table
        $torrent = $this->getTorrentByInfoHash($queries['info_hash']);

        // If Torrent Doesnt Exists Return Error to Client
        if ($torrent === null) {
            throw new TrackerException('Torrent not registered with this tracker.');
        }

        if ($user->class < Setting::get('authority.seebanned')) {
            if ($torrent->banned == 'yes') {
                throw new TrackerException("torrent banned");
            }
            if ($torrent->approval_status != Torrent::APPROVAL_STATUS_ALLOW && Setting::get('torrent.approval_status_none_visible') == 'no') {
                throw new TrackerException("torrent review not approved");
            }
        }
        return $torrent;
    }

    protected function checkPeer(Torrent $torrent, array $queries, User $user): void
    {
        if ($queries['event'] === 'completed') {
            throw new TrackerException("Torrent being announced as complete but no record found.");
        }

        $countResult = Peer::query()
            ->where('torrent', '=', $torrent->id)
            ->where('userid', $user->id)
            ->selectRaw('count(distinct(peer_id)) as counts')
            ->first()
        ;
        $counts = $countResult ? $countResult->counts : 0;
        do_log("query: " . last_query() . ", counts: $counts");
        if ($queries['left'] == 0 && $counts >= 3) {
            throw new TrackerException("You cannot seed the same torrent from more than 3 locations.");
        }
        if ($queries['left'] > 0 && $counts >= 1) {
            throw new TrackerException("You already are downloading the same torrent. You may only leech from one location at a time.");
        }
    }

    protected function checkPermission(Torrent $torrent, $queries, User $user)
    {
        if ($user->class >= User::CLASS_VIP) {
            return;
        }

        $gigs = $user->downloaded / (1024*1024*1024);
        if ($gigs < 10) {
            return;
        }
        $ratio = ($user->downloaded > 0) ? ($user->uploaded / $user->downloaded) : 1;
        $settingsMain = Setting::get('main');
        if ($settingsMain['waitsystem'] == 'yes') {
            $elapsed = Carbon::now()->diffInHours($torrent->added);
            if ($ratio < 0.4) $wait = 24;
            elseif ($ratio < 0.5) $wait = 12;
            elseif ($ratio < 0.6) $wait = 6;
            elseif ($ratio < 0.8) $wait = 3;
            else $wait = 0;

            if ($elapsed < $wait) {
                $msg = "Your ratio is too low! You need to wait " . mkprettytime($wait * 3600 - $elapsed) . " to start";
                throw new TrackerException($msg);
            }
        }

        if ($settingsMain['maxdlsystem'] == 'yes') {
            if ($ratio < 0.5) $max = 1;
            elseif ($ratio < 0.65) $max = 2;
            elseif ($ratio < 0.8) $max = 3;
            elseif ($ratio < 0.95) $max = 4;
            else $max = 0;

            if ($max > 0) {
                $countResult = Peer::query()
                    ->where('userid', $user->id)
                    ->where('seeder', 'no')
                    ->selectRaw('count(distinct(peer_id)) as counts')
                    ->first();
                $counts = $countResult ? $countResult->counts : 0;
                if ($counts > $max) {
                    $msg = "Your slot limit is reached! You may at most download $max torrents at the same time";
                    throw new TrackerException($msg);
                }
            }
        }


    }


    /**
     * @param Peer $peer
     * @param $queries
     * @throws TrackerException
     */
    protected function checkMinInterval(Peer $peer, $queries)
    {
        $lastAction = $peer->last_action;
        $isLastActionValidDate = $peer->isValidDate('last_action');
        $diffInSeconds = Carbon::now()->diffInSeconds($peer->last_action);
        $min = self::MIN_ANNOUNCE_WAIT_SECOND;
        do_log(sprintf(
            'event: %s, last_action: %s, isLastActionValidDate: %s, diffInSeconds: %s',
            $queries['event'], $lastAction, var_export($isLastActionValidDate, true), $diffInSeconds
        ));
        if ($queries['event'] == '' && $isLastActionValidDate && $diffInSeconds < $min) {
            throw new TrackerException('There is a minimum announce time of ' . $min . ' seconds');
        }
    }

    protected function checkCheater(Torrent $torrent, $queries, $dataTraffic, User $user, Peer $peer)
    {
        $settingSecurity = Setting::get('security');
        $level = $settingSecurity['cheaterdet'];
        if ($level == 0) {
            //don't do check
            return;
        }
        if ($user->class >= $settingSecurity['nodetect']) {
            //forever trust
            return;
        }
        if (!$peer->isValidDate('last_action')) {
            //no last action
            return;
        }
        $duration = Carbon::now()->diffInSeconds($peer->last_action);
        $upSpeed = $dataTraffic['uploaded_increment'] > 0 ? ($dataTraffic['uploaded_increment'] / $duration) : 0;
        $peerInfo = Arr::except($peer->toArray(), ['peer_id']);
        do_log("peerInfo: " . json_encode($peerInfo) . ", upSpeed: $upSpeed, dataTraffic: " . json_encode($dataTraffic));
        $oneGB = 1024 * 1024 * 1024;
        $tenMB = 1024 * 1024 * 10;
        $nowStr = Carbon::now()->toDateTimeString();
        $cheaterBaseData = [
            'added' => $nowStr,
            'userid' => $user->id,
            'torrentid' => $torrent->id,
            'uploaded' => $dataTraffic['uploaded_increment'],
            'downloaded' => $dataTraffic['downloaded_increment'],
            'anctime' => $duration,
            'seeders' => $torrent->seeders,
            'leechers' => $torrent->leechers,
        ];

        if ($dataTraffic['uploaded_increment'] > $oneGB && ($upSpeed > self::MUST_BE_CHEATER_SPEED / $level)) {
            //Uploaded more than 1 GB with uploading rate higher than 1024 MByte/S (For Consertive level). This is no doubt cheating.
            $comment = "User account was automatically disabled by system";
            $data = array_merge($cheaterBaseData, ['comment' => $comment]);
            Cheater::query()->insert($data);
            $modComment = "We believe you're trying to cheat. And your account is disabled.";
            $user->updateWithModComment(['enabled' => User::ENABLED_NO], $modComment);
            $userBanLog = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => "$comment(Upload speed:" . mksize($upSpeed) . "/s)"
            ];
            UserBanLog::query()->insert($userBanLog);
            throw new TrackerException($modComment);
        }

        if ($dataTraffic['uploaded_increment'] > $oneGB && ($upSpeed > self::MAY_BE_CHEATER_SPEED / $level)) {
            //Uploaded more than 1 GB with uploading rate higher than 100 MByte/S (For Consertive level). This is likely cheating.
            $comment = "Abnormally high uploading rate";
            $data = array_merge($cheaterBaseData, ['comment' => $comment]);
            $this->createOrUpdateCheater($torrent, $user, $data);
        }

        if ($level > 1) {
            if ($dataTraffic['uploaded_increment'] > $oneGB && ($upSpeed > 1024 * 1024) && ($torrent->leechers < 2 * $level)) {
                //Uploaded more than 1 GB with uploading rate higher than 1 MByte/S when there is less than 8 leechers (For Consertive level). This is likely cheating.
                $comment = "User is uploading fast when there is few leechers";
                $data = array_merge($cheaterBaseData, ['comment' => $comment]);
                $this->createOrUpdateCheater($torrent, $user, $data);
            }

            if ($dataTraffic['uploaded_increment'] > $tenMB && ($upSpeed > 1024 * 100) && ($torrent->leechers == 0)) {
                ///Uploaded more than 10 MB with uploading speed faster than 100 KByte/S when there is no leecher. This is likely cheating.
                $comment = "User is uploading when there is no leecher";
                $data = array_merge($cheaterBaseData, ['comment' => $comment]);
                $this->createOrUpdateCheater($torrent, $user, $data);
            }
        }

    }

    private function checkSeedBox(Torrent $torrent, $queries, $dataTraffic, User $user, Peer $peer)
    {
        if ($user->class >= User::CLASS_VIP || $user->isDonating()) {
            return;
        }
        $isSeedBoxRuleEnabled = Setting::get('seed_box.enabled') == 'yes';
        if (!$isSeedBoxRuleEnabled) {
            return;
        }
        $isIPSeedBox = isIPSeedBox($queries['ip'], $user->id);
        if ($isIPSeedBox) {
            return;
        }
        if (!$peer->isValidDate('last_action')) {
            //no last action
            return;
        }
        $duration = Carbon::now()->diffInSeconds($peer->last_action);
        if ($duration <= 0) {
            return;
        }
        $upSpeedMbps = number_format(($dataTraffic['uploaded_increment'] / $duration / 1024 / 1024) * 8);
        $notSeedBoxMaxSpeedMbps = Setting::get('seed_box.not_seed_box_max_speed');
        do_log("upSpeedMbps: $upSpeedMbps, notSeedBoxMaxSpeedMbps: $notSeedBoxMaxSpeedMbps");
        if ($upSpeedMbps > $notSeedBoxMaxSpeedMbps) {
            (new \App\Repositories\UserRepository())->updateDownloadPrivileges(null, $user, 'no');
            do_log("user: {$user->id} downloading privileges have been disabled! (over speed)", 'error');
            throw new TrackerException("Your downloading privileges have been disabled! (over speed)");
        }
    }

    private function createOrUpdateCheater(Torrent $torrent, User $user, array $createData)
    {
        $existsCheater = Cheater::query()
            ->where('torrentid', $torrent->id)
            ->where('userid', $user->id)
            ->where('added', '>', Carbon::now()->subHours(24))
            ->first();
        if ($existsCheater) {
            $existsCheater->increment('hit');
        } else {
            $createData['hit'] = 1;
            Cheater::query()->insert($createData);
        }
    }

    protected function isReAnnounce(Request $request): int
    {
        $key = $request->query->get('key');
        $ip = $request->query->get('ip');
        $ipv4 = $request->query->get('ipv4');
        $ipv6 = $request->query->get('ipv6');
        $queryString = $request->getQueryString();
        $lockKeyOriginal = str_replace([$key, $ip, $ipv4, $ipv6], '', $queryString);
        $lockKey = md5($lockKeyOriginal);
        $startTimestamp = nexus()->getStartTimestamp();
        $redis = Redis::connection()->client();
        $cache = $redis->get($lockKey);
        do_log("key: $key, queryString: $queryString, lockKeyOriginal: $lockKeyOriginal, startTimestamp: $startTimestamp, cache: $cache");
        if ($cache === false) {
            //new request
            $redis->set($lockKey, $startTimestamp, ['ex' => self::MIN_ANNOUNCE_WAIT_SECOND]);
            return self::ANNOUNCE_FIRST;
        } else {
            if (bcsub($startTimestamp, $cache, 3) < 0.5) {
                do_log('[DUAL]');
                return self::ANNOUNCE_DUAL;
            } else {
                do_log('[RE_ANNOUNCE]');
                return self::ANNOUNCE_DUPLICATE;
            }
        }
    }

    private function generateSuccessAnnounceResponse($torrent, $queries, $user, $withPeers = true): array
    {
        // Build Response For Bittorrent Client
        $minInterval = self::MIN_ANNOUNCE_WAIT_SECOND;
        $interval = max($this->getRealAnnounceInterval($torrent), $minInterval);
        $repDict = [
            'interval'     => $interval + random_int(10, 100),
            'min interval' => $minInterval + random_int(1, 10),
            'complete'     => (int) $torrent->seeders,
            'incomplete'   => (int) $torrent->leechers,
            'peers'        => [],
            'peers6'       => [],
        ];
        do_log("[REP_DICT_BASE] " . json_encode($repDict));

        /**
         * For non `stopped` event only
         * We query peers from database and send peer list, otherwise just quick return.
         */
        if (\strtolower($queries['event']) !== 'stopped' && $withPeers) {
            $limit = ($queries['numwant'] <= self::MAX_PEER_NUM_WANT ? $queries['numwant'] : self::MAX_PEER_NUM_WANT);
            $baseQuery = Peer::query()
                ->select(['peer_id', 'ip', 'port', 'ipv4', 'ipv6'])
                ->where('torrent', $torrent->id)
                ->where('userid', '!=', $user->id)
                ->limit($limit)
                ->orderByRaw('rand()')
            ;

            // Get Torrents Peers
            if ($queries['left'] == 0) {
                // Only include leechers in a seeder's peerlist
                $peers = $baseQuery->where('seeder', 'no')->get()->toArray();
            } else {
                $peers = $baseQuery->get()->toArray();
            }
            do_log("[REP_DICT_PEER_QUERY] " . last_query());
            $repDict['peers'] = $this->givePeers($peers, $queries['compact'], $queries['no_peer_id']);
            $repDict['peers6'] = $this->givePeers($peers, $queries['compact'], $queries['no_peer_id'], FILTER_FLAG_IPV6);
        }

        return $repDict;
    }

    private function getRealAnnounceInterval(Torrent $torrent)
    {
        $settingMain = Setting::get('main');
        $announce_wait = self::MIN_ANNOUNCE_WAIT_SECOND;
        $real_annnounce_interval = $settingMain['announce_interval'];
        $torrentSurvivalDays = Carbon::now()->diffInDays($torrent->added);
        if (
            $settingMain['anninterthreeage']
            && ($settingMain['anninterthree'] > $announce_wait)
            && ($torrentSurvivalDays >= $settingMain['anninterthreeage'])
        ) {
            $real_annnounce_interval = $settingMain['anninterthree'];
        } elseif (
            $settingMain['annintertwoage']
            && ($settingMain['annintertwo'] > $announce_wait)
            && ($torrentSurvivalDays >= $settingMain['annintertwoage'])
        ) {
            $real_annnounce_interval = $settingMain['annintertwo'];
        }
        do_log(sprintf(
            'torrent: %s, survival days: %s, real_announce_interval: %s',
            $torrent->id, $torrentSurvivalDays, $real_annnounce_interval
        ), 'debug');

        return $real_annnounce_interval;
    }

    private function getDataTraffic(Torrent $torrent, $queries, User $user, Peer $peer, $snatch): array
    {
        $torrentInfo = $torrent->toArray();
        $userInfo = $user->toArray();
        $userInfo['__is_donor'] = $user->isDonating();

        $peerInfo = [];
        if ($peer->exists) {
            $peerInfo = $peer->toArray();
        }

        $snatchInfo = [];
        if ($snatch instanceof Snatch) {
            $snatchInfo = $snatch->toArray();
        }

        $promotionInfo = apply_filter('torrent_promotion', $torrentInfo);

        return \getDataTraffic($torrentInfo, $queries, $userInfo, $peerInfo, $snatchInfo, $promotionInfo);
    }


    private function givePeers($originalPeers, $compact, $noPeerId, int $filterFlag = FILTER_FLAG_IPV4): string|array
    {
        $peers = [];
        foreach ($originalPeers as $value) {
            $ipKey = $filterFlag == FILTER_FLAG_IPV4 ? 'ipv4' : 'ipv6';
            if (!empty($value[$ipKey]) && filter_var($value[$ipKey], FILTER_VALIDATE_IP, $filterFlag)) {
                $peers[] = [
                    'peer_id' => $value['peer_id'],
                    'ip' => $value[$ipKey],
                    'port' => $value['port']
                ];
            }
        }

        if ($compact) {
            $pcomp = '';
            foreach ($peers as $p) {
                $pcomp .= \inet_pton($p['ip']);
                $pcomp .= \pack('n', (int) $p['port']);
            }
            return $pcomp;
        }

        if ($noPeerId) {
            foreach ($peers as &$p) {
                unset($p['peer_id']);
            }

            return $peers;
        }

        return $peers;
    }

    protected function generateFailedAnnounceResponse($reason): array
    {
        return [
            'failure reason' => $reason,
            'min interval'   => self::MIN_ANNOUNCE_WAIT_SECOND,
            //'retry in'     => self::MIN_ANNOUNCE_WAIT_SECOND
        ];
    }

    protected function sendFinalAnnounceResponse($repDict): \Illuminate\Http\Response
    {
        do_log("[repDict] " . nexus_json_encode($repDict));
        return \response(Bencode::encode($repDict))
            ->withHeaders(['Content-Type' => 'text/plain; charset=utf-8'])
            ->withHeaders(['Connection' => 'close'])
            ->withHeaders(['Pragma' => 'no-cache']);
    }


    /**
     *
     * @param Torrent $torrent
     * @param $queries
     * @param Peer $peer
     * @param bool $isPeerExists
     */
    private function updateTorrent(Torrent $torrent, $queries, bool $isPeerExists)
    {
        if (!empty($queries['event']) || !$isPeerExists) {
            $torrent->seeders = Peer::query()
                ->where('torrent', $torrent->id)
                ->where('to_go', '=',0)
                ->count();

            $torrent->leechers = Peer::query()
                ->where('torrent', $torrent->id)
                ->where('to_go', '>', 0)
                ->count();
        }
        $torrent->visible = Torrent::VISIBLE_YES;
        $torrent->last_action = Carbon::now();
        if ($isPeerExists && $queries['event'] == 'completed') {
            $torrent->times_completed = DB::raw("times_completed + 1");
        }
        $torrent->save();
        do_log(last_query());
    }

    private function updatePeer(Peer $peer, $queries, $isReAnnounce)
    {
        if ($queries['event'] == 'stopped') {
            Peer::query()->where('torrent', $peer->torrent)->where('peer_id', $queries['peer_id'])->delete();
            do_log(last_query());
            return;
        }
        if (!$peer->exists && $isReAnnounce == self::ANNOUNCE_DUAL) {
            do_log('[ANNOUNCE_DUAL_AND_PEER_NOT_EXISTS], return');
            return;
        }

        $nowStr = Carbon::now()->toDateTimeString();
        //torrent, userid, peer_id, ip, port, connectable, uploaded, downloaded, to_go, started, last_action, seeder, agent, downloadoffset, uploadoffset, passkey
        $update = [
            'torrent' => $peer->torrent,
            'peer_id' => $queries['peer_id'],
            'ip' => $queries['ip'],
            'userid' => $peer->userid,
            'passkey' => $peer->passkey,
            'port' => $queries['port'],
            'agent' => $queries['user_agent'],
            'connectable' => $this->getConnectable($queries['ip'], $queries['port'], $queries['user_agent'])
        ];
        if (!empty($queries['ipv4'])) {
            $update['ipv4'] = $queries['ipv4'];
        }
        if (!empty($queries['ipv6'])) {
            $update['ipv6'] = $queries['ipv6'];
        }

        if ($peer->exists) {
            $update['prev_action'] = $peer->last_action;
            $update['started'] = $peer->started;
            if ($queries['event'] == 'completed') {
                $update['finishedat'] = time();
            }
        } else {
            $update['started'] = $nowStr;
            $update['uploadoffset'] = $queries['uploaded'];
            $update['downloadoffset'] = $queries['downloaded'];
        }

        $update['to_go'] = $queries['left'];
        $update['seeder'] = $queries['left'] == 0 ? 'yes' : 'no';
        $update['last_action'] = $nowStr;
        $update['uploaded'] = $queries['uploaded'];
        $update['downloaded'] = $queries['downloaded'];

        $logData = json_encode(Arr::except($update, ['peer_id']));
        if ($peer->exists) {
            $affected = Peer::query()->where('torrent', $peer->torrent)->where('peer_id', $queries['peer_id'])->update($update);
            do_log("[UPDATE], affected: $affected, data: $logData");
        } else {
            Peer::query()->insert($update);
            do_log("[INSERT], data: $logData");
        }



//        $idArr = explode(',', $peer->ids);
//        $ipArr = explode(',', $peer->ips);
//        $logPrefix = "update: " . json_encode($update);
//        $doUpdate = false;
//        if ($peer->exists) {
//            $logPrefix .= ", [EXISTS]";
//            foreach ($idArr as $key => $id) {
//                $ip = $ipArr[$key];
//                if (isIPV4($ip) && isIPV4($queries['ip'])) {
//                    $update['ip'] = DB::raw("if(id = $id,'$ip', ip)");
//                    $doUpdate = true;
//                    $logPrefix .= ", v4, id = $id";
//                } elseif (isIPV6($ip) && isIPV6($queries['ip'])) {
//                    $update['ip'] = DB::raw("if(id = $id,'$ip', ip)");
//                    $doUpdate = true;
//                    $logPrefix .= ", v6, id = $id";
//                }
//            }
//            if ($doUpdate) {
//                $affected = Peer::query()->where('torrent', $peer->torrent)->where('peer_id', $queries['peer_id'])->update($update);
//                do_log("$logPrefix, [UPDATE], affected: $affected");
//            } else {
//                Peer::query()->insert($update);
//                do_log("$logPrefix, [INSERT]");
//            }
//        } else {
//            $logPrefix .= ", [NOT_EXISTS]";
//            Peer::query()->insert($update);
//            do_log("$logPrefix, [INSERT]");
//        }
    }

    private function getConnectable($ip, $port, $agent)
    {
        $cacheKey = 'peers:connectable:'.$ip.'-'.$port.'-'.$agent;
        $log = "cacheKey: $cacheKey";
        $hostname = $ip;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hostname = '['.$ip.']';
        }
        $connectable = Cache::get($cacheKey);
        if ($connectable === null) {
            $con = @fsockopen($hostname, $port, $error_code, $error_message, 1);
            if (is_resource($con)) {
                $connectable = Peer::CONNECTABLE_YES;
                fclose($con);
            } else {
                $connectable = Peer::CONNECTABLE_NO;
            }
            Cache::put($cacheKey, $connectable, 3600);
            $log .= ", do check, connectable: " . $connectable;
        } else {
            $log .= ", don't do check";
        }
        do_log($log);
        return $connectable;
    }

    /**
     * Update snatch, uploaded & downloaded, use the increment value  to do increment
     *
     * @param Peer $peer
     * @param $queries
     * @param $dataTraffic
     */
    private function updateSnatch(Peer $peer, $queries, $dataTraffic, $snatch)
    {
        $nowStr = Carbon::now()->toDateTimeString();

        //torrentid, userid, ip, port, uploaded, downloaded, to_go, ,seedtime, leechtime, last_action, startdat, completedat, finished
        if (!$snatch) {
            $snatch = new Snatch();
            //initial, use report uploaded + downloaded
            $snatch->torrentid = $peer->torrent;
            $snatch->userid = $peer->userid;
            $snatch->uploaded = $queries['uploaded'];
            $snatch->downloaded = $queries['downloaded'];
            $snatch->startdat = $nowStr;
        } elseif ($peer->exists) {
            //increase, use the increment value
            $snatch->uploaded = DB::raw("uploaded + " .  $dataTraffic['uploaded_increment']);
            $snatch->downloaded = DB::raw("downloaded + " .  $dataTraffic['downloaded_increment']);
            $timeIncrease = Carbon::now()->diffInSeconds($peer->last_action);
            if ($queries['left'] == 0) {
                //seeder
                $timeField = 'seedtime';
            } else {
                $timeField = 'leechtime';
            }
            $snatch->{$timeField} = DB::raw("$timeField + $timeIncrease");
            if ($queries['event'] == 'completed') {
                $snatch->completedat = $nowStr;
                $snatch->finished = 'yes';
            }
        }

        //always update
        $snatch->ip = $queries['ip'];
        $snatch->port = $queries['port'];
        $snatch->to_go = $queries['left'];
        $snatch->last_action = $nowStr;

        $snatch->save();
        do_log(last_query(), 'info');

        return $snatch;
    }

    public function scrape(Request $request): \Illuminate\Http\Response
    {
        do_log("queryString: " . $request->getQueryString());
        /**
         * Note: In Octane this class will be reused, use variable is better !!!
         */
        $userUpdates = [];
        try {
            $infoHashArr = $this->checkScrapeFields($request);
            $user = $this->checkUser($request);
            $clientAllow = $this->checkClient($request);

            if ($user->clientselect != $clientAllow->id) {
                $userUpdates['clientselect'] = $clientAllow->id;
            }
            if ($user->showclienterror == 'yes') {
                $userUpdates['showclienterror'] = 'no';
            }
            $repDict = $this->generateScrapeResponse($infoHashArr);
        } catch (ClientNotAllowedException $exception) {
            do_log("[ClientNotAllowedException] " . $exception->getMessage());
            if (isset($user) && $user->showclienterror == 'no') {
                $userUpdates['showclienterror'] = 'yes';
            }
            $repDict = $this->generateFailedAnnounceResponse($exception->getMessage());
        } catch (TrackerException $exception) {
            $repDict = $this->generateFailedAnnounceResponse($exception->getMessage());
        } catch (\Throwable $exception) {
            //other system exception
            do_log("[" . get_class($exception) . "] " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
            $repDict = $this->generateFailedAnnounceResponse("system error, report to sysop please, hint: " . nexus()->getRequestId());
        } finally {
            if (isset($user)) {
                $this->updateUser($user, $userUpdates);
            }
            return $this->sendFinalAnnounceResponse($repDict);
        }
    }

    private function checkScrapeFields(Request $request): array
    {
        preg_match_all('/info_hash=([^&]*)/i', $request->getQueryString(), $info_hash_match);

        $info_hash_array = $info_hash_match[1];
        $info_hash_original = [];
        if (count($info_hash_array) < 1) {
            throw new TrackerException("key: info_hash is Missing !");
        } else {
            foreach ($info_hash_array as $item) {
                $item = urldecode($item);
                if (($length = strlen($item)) != 20) {
                    throw new TrackerException("Invalid info_hash ! info_hash is not 20 bytes long($length)");
                }
                $info_hash_original[] = $item;
            }
        }
        return $info_hash_original;
    }

    /**
     * @param $info_hash_array
     * @return array[]
     * @see http://www.bittorrent.org/beps/bep_0048.html
     */
    private function generateScrapeResponse($info_hash_array)
    {
        $torrent_details = [];
        foreach ($info_hash_array as $item) {
            $torrent = $this->getTorrentByInfoHash($item);
            if ($torrent) {
                $torrent_details[$item] = [
                    'complete' => (int)$torrent->seeders,
                    'downloaded' => (int)$torrent->times_completed,
                    'incomplete' => (int)$torrent->leechers,
                ];
            }
        }

        return ['files' => $torrent_details];
    }

    private function getTorrentByInfoHash($infoHash)
    {
        $cacheKey = __METHOD__ . bin2hex($infoHash);
        return Cache::remember($cacheKey, 60, function () use ($infoHash, $cacheKey) {
            $fieldRaw = 'id, owner, sp_state, seeders, leechers, added, banned, hr, visible, last_action, times_completed, approval_status';
            $torrent = Torrent::query()->where('info_hash', $infoHash)->selectRaw($fieldRaw)->first();
            do_log("[getTorrentByInfoHash] cache miss [$cacheKey], from database, and get: " . ($torrent->id ?? ''));
            return $torrent;
        });
    }

    private function handleHitAndRun(User $user, Torrent $torrent, Snatch $snatch)
    {
        $now = Carbon::now();
        if ($user->class >= \App\Models\HitAndRun::MINIMUM_IGNORE_USER_CLASS) {
            return;
        }
        if ($user->isDonating()) {
            return;
        }
        $hrMode = Setting::get('hr.mode');
        if ($hrMode == HitAndRun::MODE_DISABLED) {
            return;
        }
        if ($hrMode == HitAndRun::MODE_MANUAL && $torrent->hr != Torrent::HR_YES) {
            return;
        }
        $sql = sprintf(
            "insert into `hit_and_runs` (`uid`, `torrent_id`, `snatched_id`) values(%d, %d, %d) on duplicate key update updated_at = '%s'",
            $user->id, $torrent->id, $snatch->id, $now->toDateTimeString()
        );
        DB::insert($sql);
    }

    private function updateUser(User $user, array $update)
    {
        $log = "update: " . json_encode($update);
        if (empty($update)) {
            $log .= ", no update...";
        } else {
            $user->update($update);
            $log .= ", query: " . last_query();
        }
        do_log($log, 'info');
    }

}
