<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\SeedBoxRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/viewpeerlist.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. XHR endpoint called
 * from `public/js/common.js:44`:
 *
 *     viewpeerlist(torrentid)
 *         → ajax.gets('viewpeerlist.php?id=<torrentId>')
 *
 * The result is `innerHTML`-injected into the toggle-able peer-list
 * block on `public/details.php`, so the wire shape (raw markup +
 * `text/xml` content type + no-cache headers) MUST stay stable.
 *
 * Original legacy flow (`public/viewpeerlist.php`, 240 LOC):
 *   1. `require '../include/bittorrent.php'; dbconn();
 *      require_once get_langfile_path();` bootstrap.
 *   2. Five `header()` calls forbidding all caching + `text/xml`.
 *   3. `if (isset($CURUSER))` — guest → empty body, authed → tables.
 *   4. `apply_filter('torrent_seeder_leecher_list', [], $id)` hook —
 *      lets PT plugins override the peer source.
 *   5. SELECT peers (or use the filter result).
 *   6. Reconcile `torrents.seeders` / `torrents.leechers` if the
 *      cached counts drift from the live `peers` rows.
 *   7. `usort` seeders by `uploaded` DESC, leechers by `to_go` ASC.
 *   8. Render two `dltable` blocks (seeders, leechers) with anonymity
 *      handling (privacy=strong, or torrent.anonymous=yes for the
 *      owner) and per-IP geolocation columns.
 *   9. Update `peers.is_seed_box` via a single
 *      `case id when N then BIT end` SQL when seed-box detection
 *      is enabled (`get_setting('seed_box.enabled') == 'yes'`).
 *
 * Replacement contract (this controller):
 *   - URL preserved exactly so `public/js/common.js:44`,
 *     `app/Livewire/TorrentDetail.php` (line 1498 — the modern UI's
 *     own peer-list resolver references the same wire shape), and
 *     any in-the-wild bookmarks keep working without template / JS
 *     changes.
 *   - Public route — same as the legacy script. The `$CURUSER` gate
 *     becomes a `LegacyContext::user() === null` check returning the
 *     empty-body envelope, mirroring legacy parity. Placing the route
 *     under `auth.nexus:nexus-web` would redirect guests to
 *     `/login.php?returnto=...`, and the calling JS uses synchronous
 *     `ajax.gets` whose `responseText` would then be the login-page
 *     HTML — splicing that into the toggle DIV is a UX bug we
 *     explicitly avoid (same posture as `ViewFileListController`).
 *   - `Content-Type: text/xml; charset=utf-8` and the same five
 *     no-cache headers, byte-for-byte.
 *   - 200 with empty body for: guest, missing/zero `?id`, or unknown
 *     torrent. Legacy parity — the calling JS innerHTML-replaces the
 *     content, an empty body collapses the dialog cleanly.
 *   - 200 with markup body for an authed request that resolves to a
 *     torrent: two `dltable` blocks (seeders + leechers) including
 *     header rows even when the underlying peer list is empty
 *     (legacy: rendered the leading `<b>0 Seeders</b>` / `<b>0
 *     Leechers</b>` headings even with zero rows).
 *   - `apply_filter('torrent_seeder_leecher_list', [], $id)` plugin
 *     hook is preserved verbatim.
 *   - `torrents.seeders` / `torrents.leechers` reconciliation against
 *     the resolved peer list is preserved.
 *   - `peers.is_seed_box` CASE WHEN update is preserved (only fires
 *     when `seed_box.enabled` setting is `'yes'`).
 *   - Localised column headings continue to come from
 *     `lang/<locale>/lang_viewpeerlist.php` via
 *     `get_langfile_path('viewpeerlist')`. The legacy lang
 *     dictionaries are NOT deleted in this PR; porting the 19
 *     locales to Laravel translations is deferred to Phase 5.
 *
 * Output escaping: the agent (peer client name) goes through
 * `htmlspecialchars`. The numeric counters / formatted byte sizes
 * come from trusted helpers (`mksize`, `mkprettytime`,
 * `number_format`). Usernames go through `get_username` which already
 * emits a properly escaped `<a>` tag. Geolocation strings come from
 * `get_ip_location` which the legacy template already trusts to be
 * pre-escaped.
 */
class ViewPeerListController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly SeedBoxRepository $seedBoxRepository,
    ) {}

    public function __invoke(Request $request): Response
    {
        $body = '';

        $viewer = $this->context->user();
        if ($viewer !== null) {
            $id = (int) $request->query('id', 0);
            if ($id > 0) {
                $body = $this->renderTables($id, $viewer);
            }
        }

        $response = new Response($body, 200);
        $response->headers->replace([
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').'GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);

        return $response;
    }

    private function renderTables(int $id, User $viewer): string
    {
        // Bridge into the legacy lang dictionary so per-locale column
        // headers + "Anonymous" / "Yes" / "No" / "inf" / "Seeders" /
        // "Leechers" labels keep working for all 19 locales without
        // porting them to Laravel translations. This is a deliberate,
        // documented Phase 5 deferral — see the controller PHPDoc.
        require_once get_langfile_path('viewpeerlist');

        $torrent = Torrent::query()->find(
            $id,
            ['id', 'seeders', 'leechers', 'owner', 'size', 'anonymous'],
        );
        if ($torrent === null) {
            return '';
        }

        // Plugin hook — let `torrent_seeder_leecher_list` override
        // the peer source (used by some PT plugins to back peers
        // with Redis ZSETs instead of the `peers` table).
        $filtered = apply_filter('torrent_seeder_leecher_list', [], $id);
        if (isset($filtered['seeders'], $filtered['leechers'])) {
            $seeders = $filtered['seeders'];
            $leechers = $filtered['leechers'];
            do_log('SEEDER_LEECHER_FROM_FILTER: torrent_seeder_leecher_list');
        } else {
            [$seeders, $leechers] = $this->fetchPeers($id);
        }

        $this->reconcileCounts($torrent, count($seeders), count($leechers));

        // Sort: seeders by uploaded DESC, leechers by to_go ASC
        // (smallest remaining bytes first — closest to finishing).
        usort($seeders, static function ($a, $b) {
            return ((int) $b['uploaded']) <=> ((int) $a['uploaded']);
        });
        usort($leechers, static function ($a, $b) {
            return ((int) $a['to_go']) <=> ((int) $b['to_go']);
        });

        $torrentArr = [
            'id' => $id,
            'owner' => (int) $torrent->owner,
            'size' => (int) $torrent->size,
            'anonymous' => (string) $torrent->anonymous,
        ];

        global $lang_viewpeerlist;

        $isSeedBoxCaseWhens = [];
        $seederTable = $this->renderTable(
            $lang_viewpeerlist['text_seeders'],
            $seeders,
            $torrentArr,
            $viewer,
            $isSeedBoxCaseWhens,
        );
        $leecherTable = $this->renderTable(
            $lang_viewpeerlist['text_leechers'],
            $leechers,
            $torrentArr,
            $viewer,
            $isSeedBoxCaseWhens,
        );

        $this->maybeUpdateIsSeedBox($isSeedBoxCaseWhens);

        return $seederTable.$leecherTable;
    }

    /**
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private function fetchPeers(int $id): array
    {
        $startedField = NexusDB::unixTimestampField('started');
        $lastActionField = NexusDB::unixTimestampField('last_action');
        $rows = NexusDB::select(
            'SELECT id, seeder, finishedat, downloadoffset, uploadoffset, '
            .'ip, ipv4, ipv6, port, uploaded, downloaded, to_go, '
            .$startedField.' AS st, connectable, agent, peer_id, '
            .$lastActionField.' AS la, userid '
            .'FROM peers WHERE torrent = '.$id,
        );

        $seeders = $leechers = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            if (($arr['seeder'] ?? '') === 'yes') {
                $seeders[] = $arr;
            } else {
                $leechers[] = $arr;
            }
        }

        return [$seeders, $leechers];
    }

    private function reconcileCounts(Torrent $torrent, int $seeders, int $leechers): void
    {
        if ((int) $torrent->seeders === $seeders && (int) $torrent->leechers === $leechers) {
            return;
        }
        $update = ['seeders' => $seeders, 'leechers' => $leechers];
        $original = $torrent->toJson();
        $torrent->update($update);
        do_log(sprintf(
            '[UPDATE_TORRENT_SEEDERS_LEECHERS], torrent: %d, original: %s, update: %s',
            (int) $torrent->id,
            $original,
            json_encode($update),
        ));
    }

    /**
     * @param  array<int,string>  $caseWhens  keyed by `peers.id` value
     */
    private function maybeUpdateIsSeedBox(array $caseWhens): void
    {
        if (empty($caseWhens) || get_setting('seed_box.enabled') !== 'yes') {
            return;
        }
        $sql = sprintf(
            'update peers set is_seed_box = case id %s end where id in (%s)',
            implode(' ', array_values($caseWhens)),
            implode(',', array_keys($caseWhens)),
        );
        do_log('[IS_SEED_BOX], '.$sql);
        NexusDB::statement($sql);
    }

    /**
     * @param  list<array<string,mixed>>  $peers
     * @param  array{id:int,owner:int,size:int,anonymous:string}  $torrent
     * @param  array<int,string>  $isSeedBoxCaseWhens  populated by reference; legacy parity
     */
    private function renderTable(string $heading, array $peers, array $torrent, User $viewer, array &$isSeedBoxCaseWhens): string
    {
        global $lang_viewpeerlist;

        $count = count($peers);
        $s = '<b>'.$count.' '.$heading."</b>\n";
        if ($count === 0) {
            return $s;
        }

        $enableLocationTweak = ($GLOBALS['enablelocation_tweak'] ?? '') === 'yes';
        $showLocationColumn = $enableLocationTweak || (bool) user_can('userprofile');
        $viewerId = (int) $viewer->id;

        // Pre-fetch privacy for every peer's user row in one query.
        $privacyByUserId = User::query()
            ->whereIn('id', array_column($peers, 'userid'))
            ->get(['id', 'privacy'])
            ->keyBy('id');

        $s .= "\n";
        $s .= '<table width=100% class=main border=1 cellspacing=0 cellpadding=3>'."\n";
        $s .= '<tr>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_user_ip'].'</td>';
        if ($showLocationColumn) {
            $s .= '<td class=colhead align=center>'.$lang_viewpeerlist['col_location'].'</td>';
        }
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_connectable'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_uploaded'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_rate'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_downloaded'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_rate'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_ratio'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_complete'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_connected'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_idle'].'</td>';
        $s .= '<td class=colhead align=center width=1%>'.$lang_viewpeerlist['col_client'].'</td>';
        $s .= "</tr>\n";

        $now = time();

        foreach ($peers as $e) {
            $peerUserId = (int) ($e['userid'] ?? 0);
            $privacy = $privacyByUserId->get($peerUserId)->privacy ?? '';

            $highlight = $viewerId === $peerUserId ? ' bgcolor=#BBAF9B' : '';
            $secs = max(1, (int) $e['la'] - (int) $e['st']);

            $isStrongPrivacy = $privacy === 'strong'
                || ($torrent['anonymous'] === 'yes' && $peerUserId === $torrent['owner']);
            $canView = (bool) user_can('viewanonymous') || $peerUserId === $viewerId;

            $columnLocation = '';
            $usernameSeedBoxIcon = '';
            $isSeedBox = false;

            if ($showLocationColumn) {
                [$columnLocation, $isSeedBox] = $this->renderLocationCell(
                    $e,
                    $isStrongPrivacy,
                    $canView,
                    $enableLocationTweak,
                );
            } else {
                $usernameSeedBoxIcon = $this->usernameSeedBoxIcon($e);
                $isSeedBox = $usernameSeedBoxIcon !== '';
            }

            $isSeedBoxCaseWhens[(int) $e['id']] = sprintf(
                'when %d then %d',
                (int) $e['id'],
                $isSeedBox ? 1 : 0,
            );

            // Username column with anonymity handling.
            if ($isStrongPrivacy) {
                $columnUsername = '<td class=rowfollow align=left width=1%><i>'
                    .$lang_viewpeerlist['text_anonymous']
                    .'</i>'
                    .$usernameSeedBoxIcon;
                if ($canView) {
                    $columnUsername .= '<br />('.get_username($peerUserId).')';
                }
                $columnUsername .= '</td>';
            } else {
                $columnUsername = '<td class=rowfollow align=left width=1%>'
                    .get_username($peerUserId)
                    .$usernameSeedBoxIcon
                    .'</td>';
            }

            $s .= '<tr'.$highlight.">\n";
            $s .= $columnUsername;
            $s .= $columnLocation;

            // Connectable.
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .(($e['connectable'] ?? '') === 'yes'
                    ? $lang_viewpeerlist['text_yes']
                    : '<font color=red>'.$lang_viewpeerlist['text_no'].'</font>')
                ."</nobr></td>\n";

            // Uploaded + upload rate.
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .mksize((int) $e['uploaded']).
                "</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .mksize(((int) $e['uploaded'] - (int) ($e['uploadoffset'] ?? 0)) / $secs)
                ."/s</nobr></td>\n";

            // Downloaded + download rate.
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .mksize((int) $e['downloaded'])
                ."</nobr></td>\n";
            if (($e['seeder'] ?? '') === 'no') {
                $s .= '<td class=rowfollow align=center width=1%><nobr>'
                    .mksize(((int) $e['downloaded'] - (int) ($e['downloadoffset'] ?? 0)) / $secs)
                    ."/s</nobr></td>\n";
            } else {
                $finishedDelta = max(1, (int) ($e['finishedat'] ?? 0) - (int) $e['st']);
                $s .= '<td class=rowfollow align=center width=1%><nobr>'
                    .mksize(((int) $e['downloaded'] - (int) ($e['downloadoffset'] ?? 0)) / $finishedDelta)
                    ."/s</nobr></td>\n";
            }

            // Ratio.
            if ((int) $e['downloaded'] > 0) {
                $ratio = floor(((int) $e['uploaded'] / (int) $e['downloaded']) * 1000) / 1000;
                $s .= '<td class=rowfollow align="center" width=1%><font color='
                    .get_ratio_color($ratio)
                    .'><nobr>'
                    .number_format($ratio, 3)
                    ."</nobr></font></td>\n";
            } elseif ((int) $e['uploaded'] > 0) {
                $s .= '<td class=rowfollow align=center width=1%>'
                    .$lang_viewpeerlist['text_inf']
                    ."</td>\n";
            } else {
                $s .= "<td class=rowfollow align=center width=1%>---</td>\n";
            }

            // Complete % — guard against zero-size torrents.
            $size = $torrent['size'];
            $progress = $size > 0
                ? 100 * (1 - ((int) $e['to_go'] / $size))
                : 0.0;
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .sprintf('%.2f%%', $progress)
                ."</nobr></td>\n";

            // Connected / idle times.
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .mkprettytime($now - (int) $e['st'])
                ."</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .mkprettytime($now - (int) $e['la'])
                ."</nobr></td>\n";

            // Client (peer agent).
            $s .= '<td class=rowfollow align=center width=1%><nobr>'
                .htmlspecialchars(get_agent($e['peer_id'] ?? '', $e['agent'] ?? ''))
                ."</nobr></td>\n";

            $s .= "</tr>\n";
        }

        $s .= "</table>\n";

        return $s;
    }

    /**
     * @param  array<string,mixed>  $e
     * @return array{0:string,1:bool} `[<td>cell, isSeedBox]`
     */
    private function renderLocationCell(array $e, bool $isStrongPrivacy, bool $canView, bool $enableLocationTweak): array
    {
        global $lang_functions, $lang_viewpeerlist;

        $address = [];
        $ips = [];
        $seedBoxIconSeen = '';

        if ($enableLocationTweak) {
            foreach (['ipv4', 'ipv6'] as $field) {
                if (empty($e[$field])) {
                    continue;
                }
                [$locPub] = get_ip_location($e[$field]);
                $icon = $this->seedBoxRepository->renderIcon($e[$field], $e['userid']);
                if ($icon !== '') {
                    $seedBoxIconSeen = $icon;
                }
                $address[] = $locPub.$icon;
                $ips[] = $e[$field];
            }
            $title = $canView
                ? sprintf('%s%s%s', $lang_functions['text_user_ip'] ?? 'IP', ':&nbsp;', implode(', ', $ips))
                : '';
            $location = '<div style="margin-right: 6px" title="'.$title.'">'
                .implode('<br/>', $address)
                .'</div>';
        } else {
            foreach (['ipv4', 'ipv6'] as $field) {
                if (empty($e[$field])) {
                    continue;
                }
                $icon = $this->seedBoxRepository->renderIcon($e[$field], $e['userid']);
                if ($icon !== '') {
                    $seedBoxIconSeen = $icon;
                }
                $ips[] = $e[$field].$icon;
            }
            $location = '<div style="margin-right: 6px">'.implode('<br/>', $ips).'</div>';
        }

        if ($isStrongPrivacy) {
            $result = '<div><i>'.$lang_viewpeerlist['text_anonymous'].'</i></div>';
            if ($canView) {
                $result = $location.$result;
            }
        } else {
            $result = $location;
        }

        $cell = "<td class=rowfollow align=left width=1%><div style='display: flex;white-space: nowrap;align-items: center'>"
            .$result
            .'</div></td>';

        return [$cell, $seedBoxIconSeen !== ''];
    }

    /**
     * @param  array<string,mixed>  $e
     */
    private function usernameSeedBoxIcon(array $e): string
    {
        foreach (array_filter([$e['ipv4'] ?? null, $e['ipv6'] ?? null]) as $ip) {
            $icon = $this->seedBoxRepository->renderIcon($ip, $e['userid']);
            if ($icon !== '') {
                return $icon;
            }
        }

        return '';
    }
}
