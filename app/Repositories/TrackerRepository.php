<?php
namespace App\Repositories;

use App\Exceptions\ClientNotAllowedException;
use App\Models\Cheater;
use App\Models\Peer;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Exceptions\TrackerException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\Bencode;

class TrackerRepository extends BaseRepository
{
    const MIN_ANNOUNCE_WAIT_SECOND = 30;

    const MAX_PEER_NUM_WANT = 50;

    const MUST_BE_CHEATER_SPEED = 1024 * 1024 * 1024; //1024 MB/s
    const MAY_BE_CHEATER_SPEED = 1024 * 1024 * 100; //100 MB/s

    private array $userUpdates = [];

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
        try {
            $withPeers = false;
            $queries = $this->checkAnnounceFields($request);
            $clientAllow = $this->checkClient($request);
            $user = $this->checkUser($request);
            $torrent = $this->checkTorrent($queries, $user);
            if ($this->isReAnnounce($queries) === false) {
                $withPeers = true;
                $peerSelf = $this->checkMinInterval($torrent, $queries, $user);
                if (!$peerSelf) {
                    $this->checkPeer($torrent, $queries, $user);
                    $this->checkPermission($torrent, $queries, $user);
                    $peerSelf = new Peer([
                        'torrent' => $torrent->id,
                        'peer_id' => $queries['peer_id'],
                        'userid' => $user->id,
                        'passkey' => $user->passkey,
                    ]);
                } else {
                    $this->checkCheater($torrent, $queries, $user, $peerSelf);
                }

                $this->updatePeer($peerSelf, $queries);
                $this->updateSnatch($peerSelf, $queries);
                $this->updateTorrent($torrent, $queries);

                $dataTraffic = $this->getRealUploadedDownloaded($torrent, $queries, $user, $peerSelf);
                $this->userUpdates['uploaded'] = DB::raw('uploaded + ' . $dataTraffic['uploaded']);
                $this->userUpdates['downloaded'] = DB::raw('downloaded + ' . $dataTraffic['downloaded']);
                $this->userUpdates['clientselect'] = $clientAllow->id;
                $this->userUpdates['showclienterror'] = 'no';
            }
            $repDict = $this->generateSuccessAnnounceResponse($torrent, $queries, $user, $withPeers);
        } catch (ClientNotAllowedException $exception) {
            do_log("[ClientNotAllowedException] " . $exception->getMessage());
            $this->userUpdates['showclienterror'] = 'yes';
            $repDict = $this->generateFailedAnnounceResponse($exception);
        } catch (TrackerException $exception) {
            do_log("[TrackerException] " . $exception->getMessage());
            $repDict = $this->generateFailedAnnounceResponse($exception);
        } finally {
            if (isset($user)) {
                $user->update($this->userUpdates);
            }
            return $this->sendFinalAnnounceResponse($repDict);
        }
    }

    /**
     * @param Request $request
     * @throws ClientNotAllowedException
     * @throws TrackerException
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
        $agentAllowRep->checkClient($request->peer_id, $userAgent);

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
            if (\strlen((string) $queries[$item]) !== 20) {
                throw new TrackerException("Invalid $item ! $item is not 20 bytes long");
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
        $queries['ip'] = $request->getClientIp();

        // Part.5 Get Users Agent
        $queries['user_agent'] = $request->headers->get('user-agent');

        // Part.6 bin2hex info_hash
        $queries['info_hash'] = \bin2hex($queries['info_hash']);

        // Part.7 bin2hex peer_id
        $queries['peer_id'] = \bin2hex($queries['peer_id']);

        return $queries;
    }

    protected function checkUser(Request $request)
    {
        if ($authkey = $request->query->get('authkey')) {
            list($torrentId, $uid) = $this->checkAuthkey($authkey);
            $field = 'id';
            $value = $uid;
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
            throw new TrackerException("Invalid $field: $value.");
        }
        $user->checkIsNormal();

        if ($user->parked == 'yes') {
            throw new TrackerException("Your account is parked! (Read the FAQ)");
        }
        if ($user->downloadpos == 'no') {
            throw new TrackerException("Your downloading privilege have been disabled! (Read the rules)");
        }

        return $user;
    }

    protected function checkTorrent($queries, User $user)
    {
        // Check Info Hash Against Torrents Table
        $torrent = Torrent::query()
            ->selectRaw('id, owner, sp_state, seeders, leechers, added, banned, hr, visible, last_action, times_completed')
            ->where('info_hash', '=', $queries['info_hash'])
            ->first();

        // If Torrent Doesnt Exists Return Error to Client
        if ($torrent === null) {
            throw new TrackerException('Torrent not registered with this tracker.');
        }

        if ($torrent->banned == 'yes' && $user->class < Setting::get('authority.seebanned')) {
            throw new TrackerException("torrent banned");
        }

        return $torrent;
    }

    protected function checkPeer(Torrent $torrent, array $queries, User $user): void
    {
        if ($queries['event'] === 'completed') {
            throw new TrackerException("Torrent being announced as complete but no record found.");
        }

        $counts = Peer::query()
            ->where('torrent', '=', $torrent->id)
            ->where('userid', $user->id)
            ->count();
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
            $elapsed = Carbon::now()->diffInSeconds($torrent->added);
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
                $counts = Peer::query()->where('userid', $user->id)->where('seeder', 'no')->count();
                if ($counts > $max) {
                    $msg = "Your slot limit is reached! You may at most download $max torrents at the same time";
                    throw new TrackerException($msg);
                }
            }
        }


    }

    /**
     * @param Torrent $torrent
     * @param $queries
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Builder|Model|object|null
     * @throws TrackerException
     */
    protected function checkMinInterval(Torrent $torrent, $queries, User $user)
    {
        $peer = Peer::query()
            ->where('torrent', $torrent->id)
            ->where('peer_id', $queries['peer_id'])
            ->where('userid', $user->id)
            ->first();

        if ($peer && $peer->isValidDate('prev_action') && Carbon::now()->diffInSeconds($peer->prev_action) < self::MIN_ANNOUNCE_WAIT_SECOND) {
            throw new TrackerException('There is a minimum announce time of ' . self::MIN_ANNOUNCE_WAIT_SECOND . ' seconds');
        }
        return $peer;
    }

    protected function checkCheater(Torrent $torrent, $queries, User $user, Peer $peer)
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
        $upSpeed = $queries['uploaded'] > 0 ? ($queries['uploaded'] / $duration) : 0;
        $oneGB = 1024 * 1024 * 1024;
        $tenMB = 1024 * 1024 * 10;
        $nowStr = Carbon::now()->toDateTimeString();
        $cheaterBaseData = [
            'added' => $nowStr,
            'userid' => $user->id,
            'torrentid' => $torrent->id,
            'uploaded' => $queries['uploaded'],
            'downloaded' => $queries['downloaded'],
            'anctime' => $duration,
            'seeders' => $torrent->seeders,
            'leechers' => $torrent->leechers,
        ];

        if ($queries['uploaded'] > $oneGB && ($upSpeed > self::MUST_BE_CHEATER_SPEED / $level)) {
            //Uploaded more than 1 GB with uploading rate higher than 1024 MByte/S (For Consertive level). This is no doubt cheating.
            $comment = "User account was automatically disabled by system";
            $data = array_merge($cheaterBaseData, ['comment' => $comment]);
            Cheater::query()->insert($data);
            $modComment = "We believe you're trying to cheat. And your account is disabled.";
            $user->updateWithModComment(['enabled' => User::ENABLED_NO], $modComment);
            throw new TrackerException($modComment);
        }

        if ($queries['uploaded'] > $oneGB && ($upSpeed > self::MAY_BE_CHEATER_SPEED / $level)) {
            //Uploaded more than 1 GB with uploading rate higher than 100 MByte/S (For Consertive level). This is likely cheating.
            $comment = "Abnormally high uploading rate";
            $data = array_merge($cheaterBaseData, ['comment' => $comment]);
            $this->createOrUpdateCheater($torrent, $user, $data);
        }

        if ($level > 1) {
            if ($queries['uploaded'] > $oneGB && ($upSpeed > 1024 * 1024) && ($queries['leechers'] < 2 * $level)) {
                //Uploaded more than 1 GB with uploading rate higher than 1 MByte/S when there is less than 8 leechers (For Consertive level). This is likely cheating.
                $comment = "User is uploading fast when there is few leechers";
                $data = array_merge($cheaterBaseData, ['comment' => $comment]);
                $this->createOrUpdateCheater($torrent, $user, $data);
            }

            if ($queries['uploaded'] > $tenMB && ($upSpeed > 1024 * 100) && ($queries['leechers'] == 0)) {
                ///Uploaded more than 10 MB with uploading speed faster than 100 KByte/S when there is no leecher. This is likely cheating.
                $comment = "User is uploading when there is no leecher";
                $data = array_merge($cheaterBaseData, ['comment' => $comment]);
                $this->createOrUpdateCheater($torrent, $user, $data);
            }
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

    protected function isReAnnounce(array $queries): bool
    {
        unset($queries['key']);
        $lockKey = md5(http_build_query($queries));
        $redis = Redis::connection()->client();
        if (!$redis->set($lockKey, NEXUS_START, ['nx', 'ex' => self::MIN_ANNOUNCE_WAIT_SECOND])) {
            do_log('[RE_ANNOUNCE]');
            return true;
        }
        return false;
    }

    private function generateSuccessAnnounceResponse($torrent, $queries, $user, $withPeers = true): array
    {
        // Build Response For Bittorrent Client
        $repDict = [
            'interval'     => $this->getRealAnnounceInterval($torrent),
            'min interval' => self::MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => (int) $torrent->seeders,
            'incomplete'   => (int) $torrent->leechers,
            'peers'        => [],
            'peers6'       => [],
        ];
        if (!$withPeers) {
            return $repDict;
        }

        /**
         * For non `stopped` event only
         * We query peers from database and send peer list, otherwise just quick return.
         */
        if (\strtolower($queries['event']) !== 'stopped') {
            $limit = ($queries['numwant'] <= self::MAX_PEER_NUM_WANT ? $queries['numwant'] : self::MAX_PEER_NUM_WANT);
            $baseQuery = Peer::query()
                ->select(['peer_id', 'ip', 'port'])
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

    private function getRealUploadedDownloaded(Torrent $torrent, $queries, User $user, Peer $peer): array
    {
        if ($peer->exists) {
            $realUploaded = max($queries['uploaded'] - $peer->uploaded, 0);
            $realDownloaded = max($queries['downloaded'] - $peer->downloaded, 0);
        } else {
            $realUploaded = $queries['uploaded'];
            $realDownloaded = $queries['downloaded'];
        }
        $spStateReal = $torrent->spStateReal;
        $uploaderRatio = Setting::get('torrent.uploaderdouble');
        if ($torrent->owner == $user->id) {
            //uploader, use the bigger one
            $upRatio = max($uploaderRatio, Torrent::$promotionTypes[$spStateReal]['up_multiplier']);
        } else {
            $upRatio = Torrent::$promotionTypes[$spStateReal]['up_multiplier'];
        }
        $downRatio = Torrent::$promotionTypes[$spStateReal]['down_multiplier'];
        return [
            'uploaded' => $realUploaded * $upRatio,
            'downloaded' => $realDownloaded * $downRatio
        ];
    }

    private function givePeers($peers, $compact, $noPeerId, int $filterFlag = FILTER_FLAG_IPV4): string|array
    {
        if ($compact) {
            $pcomp = '';
            foreach ($peers as $p) {
                if (isset($p['ip'], $p['port']) && \filter_var($p['ip'], FILTER_VALIDATE_IP, $filterFlag)) {
                    $pcomp .= \inet_pton($p['ip']);
                    $pcomp .= \pack('n', (int) $p['port']);
                }
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

    protected function generateFailedAnnounceResponse(\Exception $exception): array
    {
        return [
            'failure reason' => $exception->getMessage(),
            'min interval'   => self::MIN_ANNOUNCE_WAIT_SECOND,
            //'retry in'     => self::MIN_ANNOUNCE_WAIT_SECOND
        ];
    }

    protected function sendFinalAnnounceResponse($repDict): \Illuminate\Http\Response
    {
        return \response(Bencode::encode($repDict))
            ->withHeaders(['Content-Type' => 'text/plain; charset=utf-8'])
            ->withHeaders(['Connection' => 'close'])
            ->withHeaders(['Pragma' => 'no-cache']);
    }


    private function updateTorrent(Torrent $torrent, $queries)
    {
        $torrent->seeders = Peer::query()
            ->where('torrent', $torrent->id)
            ->where('to_go', '=',0)
            ->count();

        $torrent->leechers = Peer::query()
            ->where('torrent', $torrent->id)
            ->where('to_go', '>', 0)
            ->count();

        $torrent->visible = Torrent::VISIBLE_YES;
        $torrent->last_action = Carbon::now();

        if ($queries['event'] == 'completed') {
            $torrent->times_completed = DB::raw("times_completed + 1");
        }

        $torrent->save();
    }

    private function updatePeer(Peer $peer, $queries)
    {
        if ($queries['event'] == 'stopped') {
            return $peer->delete();
        }

        $nowStr = Carbon::now()->toDateTimeString();
        //torrent, userid, peer_id, ip, port, connectable, uploaded, downloaded, to_go, started, last_action, seeder, agent, downloadoffset, uploadoffset, passkey
        $peer->ip = $queries['ip'];
        $peer->port = $queries['port'];
        $peer->agent = $queries['user_agent'];
        $peer->updateConnectableStateIfNeeded();

        $peer->to_go = $queries['left'];
        $peer->seeder = $queries['left'] == 0 ? 'yes' : 'no';
        $peer->prev_action = DB::raw('last_action');
        $peer->last_action = $nowStr;
        $peer->uploaded = $queries['uploaded'];
        $peer->downloaded = $queries['downloaded'];

        if ($queries['event'] == 'started') {
            $peer->started = $nowStr;
            $peer->uploadoffset = $queries['uploaded'];
            $peer->downloadoffset = $queries['downloaded'];
        } elseif ($queries['event'] == 'completed') {
            $peer->finishat = time();
        }

        $peer->save();
    }

    private function updateSnatch(Peer $peer, $queries)
    {
        $nowStr = Carbon::now()->toDateTimeString();

        $snatch = Snatch::query()
            ->where('torrentid', $peer->torrent)
            ->where('userid', $peer->userid)
            ->first();

        //torrentid, userid, ip, port, uploaded, downloaded, to_go, ,seedtime, leechtime, last_action, startdat, completedat, finished
        if (!$snatch) {
            $snatch = new Snatch();
            //initial
            $snatch->torrentid = $peer->torrent;
            $snatch->userid = $peer->userid;
            $snatch->uploaded = $queries['uploaded'];
            $snatch->downloaded = $queries['downloaded'];
            $snatch->startat = $nowStr;
        } else {
            //increase
            $snatch->uploaded = DB::raw("uploaded + " .  $queries['uploaded']);
            $snatch->downloaded = DB::raw("downloaded + " .  $queries['downloaded']);
            $timeIncrease = Carbon::now()->diffInSeconds($peer->last_action);
            if ($queries['left'] == 0) {
                //seeder
                $timeField = 'seedtime';
            } else {
                $timeField = 'leechtime';
            }
            $snatch->{$timeField} = DB::raw("$timeField + $timeIncrease");
        }

        //always update
        $snatch->ip = $queries['ip'];
        $snatch->port = $queries['port'];
        $snatch->to_go = $queries['to_go'];
        $snatch->last_action = $nowStr;
        if ($queries['event'] == 'completed') {
            $snatch->completedat = $nowStr;
            $snatch->finished = 'yes';
        }

        $snatch->save();
    }

}
