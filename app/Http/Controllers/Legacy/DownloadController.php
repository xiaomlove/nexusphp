<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Torrent;
use App\Repositories\IpLogRepository;
use App\Repositories\TorrentRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\TorrentFile;

/**
 * Replacement for `public/download.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * The torrent download endpoint — every `.torrent` file the site
 * serves goes through here. Three auth modes:
 *
 *   1. `?downhash=UID.HASH` — RSS / external client mode. The
 *      payload is split on `.` into `(userId, encryptedHash)`;
 *      `TorrentRepository::decryptDownHash()` decrypts it back
 *      into the torrent id. Used by RSS feeds where the user
 *      isn't holding a session cookie.
 *   2. `?passkey=...&id=N` — passkey mode (gated on
 *      `torrent.download_support_passkey='yes'`). Looks up the
 *      user by passkey, accepts the explicit `?id=N`. Used by
 *      external download managers / IYUU bots.
 *   3. `?id=N` — standard session auth. Falls into the
 *      `loggedinorreturn()` branch, with three pre-download
 *      interstitial gates (`showdlnotice`, `showclienterror`,
 *      `leechwarn`) that 302 to `/downloadnotice.php` until the
 *      user clicks through. The `?letdown=1` flag bypasses the
 *      interstitials (set by `DownloadNoticeController` after the
 *      user submits the confirmation form).
 *
 * Original legacy flow (`public/download.php`, 214 LOC):
 *   1. Three-branch auth setup (described above) ending with
 *      `$CURUSER` populated.
 *   2. `IpLogRepository::saveToCache()` of the request IP.
 *   3. UPDATE `users.last_access = NOW()`, `users.ip = <client>`.
 *   4. `denyDownload()` (= `permissiondenied()`) when
 *      `users.downloadpos = 'no'`.
 *   5. SELECT torrent row + category mode; `httperr()` when the
 *      row, file, or filesystem is not readable.
 *   6. Banned/approval/`can_access_torrent` gate.
 *   7. UPDATE `torrents.hits = hits + 1`.
 *   8. Lazily mint a passkey when missing.
 *   9. `TorrentFile::load()` the .torrent file, override
 *      announce/comment/createdBy/creationDate, dump back as
 *      `application/x-bittorrent`.
 *
 * Replacement contract:
 *   - URL preserved exactly so:
 *     - `app/Livewire/TorrentDetail.php` (the modern UI download
 *       button — which links to `/download.php?id=N`),
 *     - `app/Repositories/TorrentRepository.php` (the URL builder
 *       used by RSS feeds),
 *     - `app/Support/Http.php` (the URL builder),
 *     - `database/seeders/FaqTableSeeder.php` (FAQ entries),
 *     - 19 `lang/<locale>/lang_index.php` rendered links,
 *     - `include/functions.php` / `include/globalfunctions.php`
 *       references,
 *     keep working without template changes.
 *   - The route lives OUTSIDE `auth.nexus:nexus-web` middleware
 *     because the downhash and passkey modes authenticate the
 *     viewer themselves, bypassing the session cookie. The
 *     standard `?id=` mode falls through to a `LegacyContext`
 *     check that mirrors the legacy `loggedinorreturn()` redirect
 *     to `/login.php`.
 *   - GET-only — the legacy script never had a POST branch.
 *   - Validation tightened: invalid downhash / unknown passkey /
 *     missing id return 400 (legacy: `die("...")` HTTP 200).
 *     `httperr()` paths still return 404.
 */
class DownloadController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly TorrentRepository $torrentRepository,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // Three-way auth. After this block `$user` is populated as
        // an array (matching the legacy `$CURUSER` shape) and `$id`
        // is the torrent id we're serving.
        [$user, $id, $needsInterstitial] = $this->resolveAuth($request);
        if ($needsInterstitial !== null) {
            // Standard session-auth mode triggered an interstitial
            // redirect. Return it as-is.
            return $needsInterstitial;
        }

        // Bridge `$user` into `$GLOBALS['CURUSER']` so legacy
        // helpers (`can_access_torrent`, `do_log`, etc.) keep
        // seeing the resolved user even in the downhash/passkey
        // branches that bypass the session.
        $GLOBALS['CURUSER'] = $user;

        IpLogRepository::saveToCache((int) $user['id']);
        NexusDB::table('users')->where('id', (int) $user['id'])->update([
            'last_access' => NexusDB::raw('NOW()'),
            'ip' => (string) $user['ip'],
        ]);

        if (($user['downloadpos'] ?? 'yes') === 'no') {
            abort(403, 'Permission denied.');
        }

        $row = $this->loadTorrentRow($id);
        $fn = $this->resolveTorrentFile($id);
        $this->checkAccess($row, $user);

        NexusDB::table('torrents')->where('id', $id)->increment('hits');

        // Lazily mint a passkey when missing — same shape as legacy.
        if (strlen((string) ($user['passkey'] ?? '')) !== 32) {
            $user['passkey'] = md5(
                ((string) $user['username'])
                .date('Y-m-d H:i:s')
                .((string) ($user['passhash'] ?? '')),
            );
            NexusDB::table('users')->where('id', (int) $user['id'])->update([
                'passkey' => (string) $user['passkey'],
            ]);
        }

        return $this->buildTorrentResponse($fn, $row, $user, $id);
    }

    /**
     * @return array{0:array<string,mixed>,1:int,2:RedirectResponse|null}
     *                                                                    `[user, torrentId, interstitialRedirect]`
     */
    private function resolveAuth(Request $request): array
    {
        // Branch 1: downhash mode (RSS / external).
        $downhash = (string) $request->input('downhash', '');
        if ($downhash !== '') {
            return [$this->authByDownhash($downhash), $this->torrentIdFromDownhash($downhash), null];
        }

        // Branch 2: passkey mode (when enabled).
        if (get_setting('torrent.download_support_passkey') === 'yes'
            && (string) $request->input('passkey', '') !== ''
            && $request->input('id') !== null) {
            return [
                $this->authByPasskey((string) $request->input('passkey', '')),
                (int) $request->input('id', 0),
                null,
            ];
        }

        // Branch 3: standard session auth.
        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            abort(404);
        }
        $viewer = $this->context->user();
        if ($viewer === null) {
            return [
                [],
                $id,
                redirect('/login.php?returnto='.urlencode($request->fullUrl())),
            ];
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        // Pre-download interstitial gates. The `?letdown=1` flag
        // (set by `DownloadNoticeController` on the redirect after
        // the user clicks through) bypasses the gates.
        $letDown = (int) $request->query('letdown', 0) === 1;
        if (! $letDown) {
            $hop = $this->maybeInterstitialRedirect($viewer, $id);
            if ($hop !== null) {
                return [[], $id, $hop];
            }
        }

        // Convert the typed viewer into the array shape the rest
        // of the controller (and the legacy helpers it forwards
        // to) expect.
        $user = $viewer->toLegacyArray();
        $user['ip'] = function_exists('getip') ? (string) getip() : (string) $request->ip();

        return [$user, $id, null];
    }

    /**
     * @return array<string,mixed>
     */
    private function authByDownhash(string $downhash): array
    {
        $parts = explode('.', $downhash, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            abort(400, 'invalid downhash, format error');
        }

        $userObj = NexusDB::table('users')->where('id', (int) $parts[0])->first();
        if ($userObj === null) {
            abort(400, 'invalid uid');
        }
        $user = (array) $userObj;
        if (($user['enabled'] ?? 'yes') === 'no' || ($user['parked'] ?? 'no') === 'yes') {
            abort(403, 'account disabled or parked');
        }

        $user['ip'] = function_exists('getip') ? (string) getip() : '';

        $decrypted = $this->torrentRepository->decryptDownHash($parts[1], $user);
        if (empty($decrypted)) {
            if (function_exists('do_log')) {
                @do_log('downhash invalid: '.$downhash);
            }
            abort(400, 'invalid downhash, decrypt fail');
        }

        return $user;
    }

    private function torrentIdFromDownhash(string $downhash): int
    {
        $parts = explode('.', $downhash, 2);
        $userObj = NexusDB::table('users')->where('id', (int) $parts[0])->first();
        if ($userObj === null) {
            abort(400, 'invalid uid');
        }
        $user = (array) $userObj;
        $user['ip'] = function_exists('getip') ? (string) getip() : '';
        $decrypted = $this->torrentRepository->decryptDownHash($parts[1], $user);

        return (int) ($decrypted[0] ?? 0);
    }

    /**
     * @return array<string,mixed>
     */
    private function authByPasskey(string $passkey): array
    {
        $userObj = NexusDB::table('users')->where('passkey', $passkey)->first();
        if ($userObj === null) {
            abort(400, 'invalid passkey');
        }
        $user = (array) $userObj;
        if (($user['enabled'] ?? 'yes') === 'no' || ($user['parked'] ?? 'no') === 'yes') {
            abort(403, 'account disabled or parked');
        }
        $user['ip'] = function_exists('getip') ? (string) getip() : '';

        return $user;
    }

    /**
     * Translate the legacy "showdlnotice / showclienterror /
     * leechwarn" booleans into a 302 to the matching
     * `/downloadnotice.php?type=...` page. Returns null when none
     * of the gates fire.
     */
    private function maybeInterstitialRedirect($viewer, int $torrentId): ?RedirectResponse
    {
        $base = '/downloadnotice.php?torrentid='.$torrentId;
        if ((int) ($viewer->showdlnotice ?? 0) === 1) {
            return redirect($base.'&type=firsttime');
        }
        if (((string) ($viewer->showclienterror ?? 'no')) === 'yes') {
            return redirect($base.'&type=client');
        }
        if (((string) ($viewer->leechwarn ?? 'no')) === 'yes') {
            return redirect($base.'&type=ratio');
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadTorrentRow(int $id): array
    {
        $obj = NexusDB::table('torrents')
            ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
            ->where('torrents.id', $id)
            ->select([
                'torrents.name', 'torrents.filename', 'torrents.save_as',
                'torrents.size', 'torrents.owner', 'torrents.banned',
                'torrents.approval_status', 'torrents.price',
                'torrents.added', 'categories.mode AS search_box_id',
            ])
            ->first();
        if ($obj === null) {
            if (function_exists('do_log')) {
                @do_log('[TORRENT_NOT_EXISTS_IN_DATABASE] '.$id);
            }
            abort(404);
        }

        return (array) $obj;
    }

    private function resolveTorrentFile(int $id): string
    {
        $torrentDir = (string) ($GLOBALS['torrent_dir'] ?? 'torrents');
        $relative = $torrentDir.'/'.$id.'.torrent';
        $fn = function_exists('getFullDirectory')
            ? (string) getFullDirectory($relative)
            : base_path($relative);

        if (! is_file($fn)) {
            if (function_exists('do_log')) {
                @do_log('[TORRENT_NOT_EXISTS_IN_PATH] '.$fn, 'error');
            }
            abort(404);
        }
        if (! is_readable($fn)) {
            if (function_exists('do_log')) {
                @do_log('[TORRENT_NOT_READABLE] '.$fn, 'error');
            }
            abort(404);
        }
        if (filesize($fn) === 0) {
            if (function_exists('do_log')) {
                @do_log('[TORRENT_NOT_VALID_SIZE_ZERO] '.$fn, 'error');
            }
            abort(404);
        }

        return $fn;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $user
     */
    private function checkAccess(array $row, array $user): void
    {
        $approvalNotAllowed = ((int) $row['approval_status'] !== Torrent::APPROVAL_STATUS_ALLOW)
            && get_setting('torrent.approval_status_none_visible') === 'no';
        $allowOwnerDownload = (int) $row['owner'] === (int) $user['id'];
        $canSeedBanned = function_exists('user_can') && user_can('seebanned');
        $canAccessTorrent = function_exists('can_access_torrent')
            ? (bool) can_access_torrent($row, (int) $user['id'])
            : true;

        $bannedBlock = (($row['banned'] ?? 'no') === 'yes'
            || ($approvalNotAllowed && ! $allowOwnerDownload))
            && ! $canSeedBanned;

        if ($bannedBlock || ! $canAccessTorrent) {
            if (function_exists('do_log')) {
                @do_log(sprintf(
                    '[DENY_DOWNLOAD], user: %s, approvalNotAllowed: %s, allowOwnerDownload: %s, canSeedBanned: %s, canAccessTorrent: %s',
                    (int) $user['id'],
                    var_export($approvalNotAllowed, true),
                    var_export($allowOwnerDownload, true),
                    var_export($canSeedBanned, true),
                    var_export($canAccessTorrent, true),
                ), 'error');
            }
            abort(403, 'Permission denied.');
        }
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $user
     */
    private function buildTorrentResponse(string $fn, array $row, array $user, int $id): Response
    {
        $dict = TorrentFile::load($fn);
        $dict->cleanRootFields();

        $trackerHost = function_exists('get_tracker_schema_and_host')
            ? (string) get_tracker_schema_and_host($user['tracker_url_id'] ?? null, true)
            : '';
        $dict->setAnnounce($trackerHost.'?passkey='.((string) $user['passkey']));

        $detailsBase = function_exists('getSchemeAndHttpHost')
            ? (string) getSchemeAndHttpHost(true)
            : '';
        $dict->setComment($detailsBase.'/details.php?id='.$id);

        $siteName = (string) ($GLOBALS['SITENAME'] ?? '');
        $dict->setCreatedBy($siteName);
        $dict->setCreationDate(strtotime((string) ($row['added'] ?? 'now')) ?: time());

        if (function_exists('do_log')) {
            @do_log(sprintf(
                '[ANNOUNCE_URL], user: %s, torrent: %s, url: %s',
                (int) $user['id'], $id, $dict->getAnnounce(),
            ));
        }

        $namePrefix = (string) ($GLOBALS['torrentnameprefix'] ?? '');
        $disposition = function_exists('make_content_disposition')
            ? (string) make_content_disposition($namePrefix.((string) $row['save_as']).'.torrent')
            : 'attachment; filename="'.((string) $row['save_as']).'.torrent"';

        return new Response($dict->dumpToString(), Response::HTTP_OK, [
            'Content-Type' => 'application/x-bittorrent',
            'Content-Disposition' => $disposition,
        ]);
    }
}
