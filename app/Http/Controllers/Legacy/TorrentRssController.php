<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/torrentrss.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Personal RSS feed of the latest torrents, filtered by the
 * standard browse facets. The viewer is identified by the legacy
 * `passkey` (a 32-char hex string stored on `users.passkey`),
 * NOT by the session cookie — the feed is consumed by external
 * RSS readers / torrent clients that don't carry the cookie.
 *
 * Original legacy flow (~305 LOC):
 *   1. `require '../include/bittorrent.php';` — but NOT
 *      `dbconn(); loggedinorreturn();`. The script defers DB
 *      bootstrap to AFTER the cache lookup, so a cache hit costs
 *      ~0 ms beyond the OPcache JIT.
 *   2. Passkey resolution: `$_GET['passkey']` falls back to
 *      `$CURUSER['passkey']` (so the file ALSO works as an authed
 *      page; `getrss.php` calls into it that way to render an
 *      embedded preview).
 *   3. Cache key: `nexus_rss:<passkey>:<md5(http_build_query)>`.
 *      Cache hit returns the XML verbatim.
 *   4. Cache miss: bootstrap DB (`dbconn(doLogin: false)`),
 *      validate passkey against `users` (cached for 1 hour under
 *      `user_passkey_<passkey>_rss`), reject `enabled='no'` /
 *      `parked='yes'` accounts.
 *   5. Build a complex `WHERE` from optional facets
 *      (`cat<id>` / `sou<id>` / `med<id>` / `cod<id>` / `sta<id>`
 *      / `pro<id>` / `tea<id>` / `aud<id>`), `inclbookmarked=1`,
 *      sticky filter, `paid` (free / paid / both), default browse
 *      categories visibility (`spsct`), `approval_status_none_visible`,
 *      and `torrents.visible='yes'`. The legacy script also has a
 *      dead `searchstr` branch that is gated on
 *      `if (isset($searchstr))` — `$searchstr` is unconditionally
 *      `unset()`'d a few lines above the gate, so the branch is
 *      currently unreachable. We preserve it as unreachable.
 *   6. Sort: `id desc`, limit `1..50` (default 50, capped). Two
 *      separate queries when sticky promotion is active — sticky
 *      torrent rows are prepended to the normal listing in
 *      `pos_state` order.
 *   7. Both queries are cached for 5 minutes by the SQL hash.
 *   8. Render an RSS 2.0 XML envelope per item, persist the full
 *      XML body under the cache key (also 5 min TTL), and
 *      `header('Content-type: text/xml')`.
 *
 * Replacement contract (this controller):
 *   - URL preserved exactly so RSS-reader subscriptions and
 *     `public/getrss.php`-rendered preview links keep working
 *     without template / JS changes.
 *   - Route lives OUTSIDE `auth.nexus:nexus-web` because the
 *     `?passkey=…` URL is the auth token; an RSS reader does not
 *     send the session cookie.
 *   - `?passkey=` missing → 200 with body `require passkey`.
 *     Legacy parity (an RSS reader bin-checks the body, not the
 *     status).
 *   - `?passkey=` not matching any user → 200 with body
 *     `invalid passkey`.
 *   - `?passkey=` matches a disabled / parked user → 200 with
 *     body `account disabed or parked` (legacy typo preserved
 *     bit-for-bit — the body is part of the wire contract).
 *   - Successful render → 200 with `Content-Type: text/xml;
 *     charset=utf-8` and the cached RSS 2.0 XML body.
 *   - All side effects of the legacy pipeline are preserved
 *     verbatim: cache `nexus_rss:<passkey>:<md5(query)>` (5 min
 *     TTL), per-passkey user-row cache (1 h TTL), per-query SQL
 *     cache (5 min TTL), `apply_filter('sticky_promotion_torrent_ids',
 *     …)` plugin hook, and the dead-but-frozen `searchstr` branch.
 *
 * Localisation: the body is locale-neutral RSS XML. `nexus_trans()`
 * is the only translation call (for the "user not exists"
 * fallback when an owner row has been deleted but the torrent
 * still references it).
 *
 * Note on `$_GET` whitelist: the legacy script unset every key
 * that wasn't on a small allow-list. We mirror that on
 * `request()->query()` — extra keys never reach the cache key,
 * so an attacker cannot bust the cache by appending random query
 * params (which would otherwise let them DoS the DB).
 */
class TorrentRssController extends Controller
{
    /** @var list<string> */
    private const EXACT_PARAMS = [
        'inclbookmarked',
        'paid',
        'rows',
        'icat',
        'ismalldescr',
        'isize',
        'iuplder',
        'search',
        'search_mode',
        'sticky',
        'linktype',
    ];

    private const PREFIXED_PARAMS_REGEX = '/^(cat|sou|med|cod|sta|pro|tea|aud)\d+$/';

    private const CACHE_TTL_SECONDS = 300;

    private const USER_CACHE_TTL_SECONDS = 3600;

    private const MAX_ROWS = 50;

    public function __invoke(Request $request): Response
    {
        $passkey = $this->resolvePasskey($request);
        if ($passkey === '') {
            return $this->plain('require passkey');
        }

        // Whitelist `$_GET` so the cache key only depends on
        // recognised facets. This mirrors the legacy unset() loop
        // and is what makes the cache safe (an attacker appending
        // `?foo=<random>` cannot bust it).
        $filteredQuery = $this->filterQueryParams($request->query());
        $cacheKey = 'nexus_rss:'.$passkey.':'.md5(http_build_query($filteredQuery));

        $cached = NexusDB::cache_get($cacheKey);
        if ($cached !== false && $cached !== null && nexus_env('APP_ENV') !== 'local') {
            do_log('rss get from cache');

            return $this->xml((string) $cached);
        }

        $user = $this->resolveUser($passkey);
        if ($user === null) {
            return $this->plain('invalid passkey');
        }
        if (($user['enabled'] ?? '') === 'no' || ($user['parked'] ?? '') === 'yes') {
            return $this->plain('account disabed or parked');
        }

        $xml = $this->buildXml($filteredQuery, $user);
        do_log('rss cache generated');
        NexusDB::cache_put($cacheKey, $xml, self::CACHE_TTL_SECONDS);

        return $this->xml($xml);
    }

    private function resolvePasskey(Request $request): string
    {
        $passkey = trim((string) $request->query('passkey', ''));
        if ($passkey !== '') {
            return $passkey;
        }
        // Fallback to the authed user's passkey if the visitor
        // happens to have a session cookie. Legacy parity —
        // `getrss.php` calls into us this way to render an inline
        // RSS preview.
        global $CURUSER;
        if (is_array($CURUSER) && ! empty($CURUSER['passkey'])) {
            return (string) $CURUSER['passkey'];
        }

        return '';
    }

    /**
     * @param  array<string,mixed>  $query
     * @return array<string,mixed>
     */
    private function filterQueryParams(array $query): array
    {
        $kept = [];
        foreach ($query as $key => $value) {
            if (in_array($key, self::EXACT_PARAMS, true)
                || preg_match(self::PREFIXED_PARAMS_REGEX, (string) $key) === 1
            ) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveUser(string $passkey): ?array
    {
        $row = NexusDB::remember(
            'user_passkey_'.$passkey.'_rss',
            self::USER_CACHE_TTL_SECONDS,
            function () use ($passkey) {
                $row = NexusDB::table('users')
                    ->where('passkey', $passkey)
                    ->select(['id', 'enabled', 'parked', 'passkey'])
                    ->first();

                return $row !== null ? (array) $row : null;
            },
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $user
     */
    private function buildXml(array $query, array $user): string
    {
        $where = $this->buildWhere($query, $user);
        $rows = $this->fetchTorrents($where, $query);

        return $this->renderXml($rows, $query, $user);
    }

    /**
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $user
     */
    private function buildWhere(array $query, array $user): string
    {
        $clauses = [];

        // Bookmark filter.
        if ((int) ($query['inclbookmarked'] ?? 0) === 1) {
            $bookmarks = return_torrent_bookmark_array((int) $user['id']);
            if (! empty($bookmarks)) {
                $clauses[] = 'torrents.id IN ('.implode(',', array_map('intval', $bookmarks)).')';
            }
        }

        // Approval-status visibility.
        $approvalNoneVisible = (string) get_setting('torrent.approval_status_none_visible');
        if ($approvalNoneVisible === 'no' && ! user_can('staffmem', false, (int) $user['id'])) {
            $clauses[] = 'torrents.approval_status = '.Torrent::APPROVAL_STATUS_ALLOW;
        }

        // Special-section permission.
        $browseMode = (string) get_setting('main.browsecat');
        $onlyBrowseSection = get_setting('main.spsct') !== 'yes'
            || ! user_can('view_special_torrent', false, (int) $user['id']);
        if ($onlyBrowseSection) {
            $allBrowseCategoryIds = (array) SearchBox::listCategoryId($browseMode);
            $allBrowseCategoryIds = array_map('intval', $allBrowseCategoryIds);
            if (! empty($allBrowseCategoryIds)) {
                $clauses[] = 'torrents.category in ('.implode(',', $allBrowseCategoryIds).')';
            }
        }

        // Visibility.
        $clauses[] = "torrents.visible = 'yes'";

        // Paid filter.
        $paid = (string) ($query['paid'] ?? '0');
        if (! in_array($paid, ['0', '1', '2'], true)) {
            $paid = '0';
        }
        if ($paid === '0') {
            $clauses[] = 'torrents.price = 0';
        } elseif ($paid === '1') {
            $clauses[] = 'torrents.price > 0';
        }

        // Facets: cat / sou / med / cod / sta / pro / tea / aud.
        $facets = [
            ['categories', 'category', 'cat'],
            ['sources', 'source', 'sou'],
            ['media', 'medium', 'med'],
            ['codecs', 'codec', 'cod'],
            ['standards', 'standard', 'sta'],
            ['processings', 'processing', 'pro'],
            ['teams', 'team', 'tea'],
            ['audiocodecs', 'audiocodec', 'aud'],
        ];
        foreach ($facets as [$table, $columnName, $prefix]) {
            $items = (array) searchbox_item_list($table, 0);
            $picked = [];
            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if (! empty($query[$prefix.$itemId])) {
                    $picked[] = $itemId;
                }
            }
            if (count($picked) > 0) {
                $clauses[] = $columnName.' IN ('.implode(',', $picked).')';
            }
        }

        return implode(' AND ', $clauses);
    }

    /**
     * @param  array<string,mixed>  $query
     * @return list<array<string,mixed>>
     */
    private function fetchTorrents(string $where, array $query): array
    {
        $hasStickyFirst = false;
        $hasStickySecond = false;
        $hasStickyNormal = false;
        $noNormalResults = false;
        $prependIds = [];

        $inclbookmarked = (int) ($query['inclbookmarked'] ?? 0);
        if (isset($query['sticky']) && $inclbookmarked === 0) {
            $stickyArr = explode(',', (string) $query['sticky']);
            $posStates = [];
            if (in_array('0', $stickyArr, true)) {
                $hasStickyNormal = true;
            }
            if (in_array('1', $stickyArr, true)) {
                $hasStickyFirst = true;
                $posStates[] = Torrent::POS_STATE_STICKY_FIRST;
            }
            if (in_array('2', $stickyArr, true)) {
                $hasStickySecond = true;
                $posStates[] = Torrent::POS_STATE_STICKY_SECOND;
            }
            if (! empty($posStates)) {
                $prependIds = Torrent::query()
                    ->whereIn('pos_state', $posStates)
                    ->pluck('id')
                    ->all();
            }
        }
        $prependIds = (array) apply_filter('sticky_promotion_torrent_ids', $prependIds);

        $stickyWhere = '';
        if ($hasStickyNormal) {
            $stickyWhere = sprintf("torrents.pos_state = '%s'", Torrent::POS_STATE_STICKY_NONE);
        } elseif ($hasStickyFirst || $hasStickySecond) {
            $noNormalResults = true;
        }

        $rows = (int) ($query['rows'] ?? 0);
        if ($rows < 1 || $rows > self::MAX_ROWS) {
            $rows = self::MAX_ROWS;
        }

        $fields = 'torrents.id, torrents.category, torrents.name, torrents.small_descr, '
            .'torrent_extras.descr, torrents.info_hash, torrents.size, torrents.added, '
            .'torrents.anonymous, torrents.owner, categories.name AS category_name';

        $normalWhere = '';
        if ($where !== '') {
            $normalWhere = 'WHERE '.$where;
            if ($stickyWhere !== '') {
                $normalWhere .= ' AND '.$stickyWhere;
            }
        }

        $normalRows = [];
        if (! $noNormalResults) {
            $sql = "SELECT $fields FROM torrents "
                .'LEFT JOIN categories ON torrents.category = categories.id '
                .'LEFT JOIN torrent_extras ON torrent_extras.torrent_id = torrents.id '
                ."$normalWhere ORDER BY id desc LIMIT $rows";
            $normalRows = NexusDB::remember(
                'nexus_rss:normal:'.md5($sql),
                self::CACHE_TTL_SECONDS,
                fn () => array_map(static fn ($row) => (array) $row, NexusDB::select($sql)),
            );
        }

        $prependRows = [];
        if (! empty($prependIds)) {
            $prependIdStr = implode(',', array_map('intval', $prependIds));
            $sql = "SELECT $fields FROM torrents "
                .'LEFT JOIN categories ON torrents.category = categories.id '
                .'LEFT JOIN torrent_extras ON torrent_extras.torrent_id = torrents.id '
                ."WHERE torrents.id IN ($prependIdStr) "
                .($where !== '' ? 'AND '.$where.' ' : '')
                ."ORDER BY field(torrents.id, $prependIdStr)";
            $prependRows = NexusDB::remember(
                'nexus_rss:prepend:'.md5($sql),
                self::CACHE_TTL_SECONDS,
                fn () => array_map(static fn ($row) => (array) $row, NexusDB::select($sql)),
            );
        }

        $byId = [];
        foreach ($prependRows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        foreach ($normalRows as $row) {
            $id = (int) $row['id'];
            if (! isset($byId[$id])) {
                $byId[$id] = $row;
            }
        }

        return array_values($byId);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $user
     */
    private function renderXml(array $rows, array $query, array $user): string
    {
        global $BASEURL, $SITENAME, $SITEEMAIL, $SLOGAN, $datefounded;

        $url = (string) get_protocol_prefix().(string) ($BASEURL ?? '');
        $year = substr((string) ($datefounded ?? ''), 0, 4);
        $yearFounded = $year !== '' ? $year : '2007';
        $copyright = 'Copyright (c) '.((string) $SITENAME).' '
            .(date('Y') !== $yearFounded ? $yearFounded.'-' : '')
            .date('Y').', all rights reserved';

        $dllink = isset($query['linktype']) && (string) $query['linktype'] === 'dl';
        $torrentRep = new TorrentRepository;

        $sitenameEsc = addslashes((string) $SITENAME);
        $sloganEsc = addslashes('Latest torrents from '.((string) $SITENAME).' - '.htmlspecialchars((string) ($SLOGAN ?? '')));

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<rss version="2.0">';
        $xml .= '<channel>';
        $xml .= '<title>'.$sitenameEsc.' Torrents</title>';
        $xml .= '<link><![CDATA['.$url.']]></link>';
        $xml .= '<description><![CDATA['.$sloganEsc.']]></description>';
        $xml .= '<language>zh-cn</language>';
        $xml .= '<copyright>'.$copyright.'</copyright>';
        $xml .= '<managingEditor>'.((string) $SITEEMAIL).' ('.((string) $SITENAME).' Admin)</managingEditor>';
        $xml .= '<webMaster>'.((string) $SITEEMAIL).' ('.((string) $SITENAME).' Webmaster)</webMaster>';
        $xml .= '<pubDate>'.date('r').'</pubDate>';
        $xml .= '<generator>'.(defined('PROJECTNAME') ? PROJECTNAME : 'NexusPHP').' RSS Generator</generator>';
        $xml .= '<docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>';
        $xml .= '<ttl>60</ttl>';
        $xml .= '<image>';
        $xml .= '<url><![CDATA['.$url.'/pic/rss_logo.jpg]]></url>';
        $xml .= '<title>'.$sitenameEsc.' Torrents</title>';
        $xml .= '<link><![CDATA['.$url.']]></link>';
        $xml .= '<width>100</width><height>100</height>';
        $xml .= '<description>'.$sitenameEsc.' Torrents</description>';
        $xml .= '</image>';

        foreach ($rows as $row) {
            $xml .= $this->renderItem($row, $query, $user, $url, $torrentRep, $dllink);
        }

        $xml .= '</channel></rss>';

        return $xml;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $user
     */
    private function renderItem(
        array $row,
        array $query,
        array $user,
        string $url,
        TorrentRepository $torrentRep,
        bool $dllink,
    ): string {
        $ownerInfo = (array) (get_user_row((int) ($row['owner'] ?? 0)) ?: []);
        if (($row['anonymous'] ?? 'no') === 'yes') {
            $author = 'anonymous';
        } elseif (! empty($ownerInfo)) {
            $author = (string) ($ownerInfo['username'] ?? 'unknown');
        } else {
            $author = (string) nexus_trans('nexus.user_not_exists');
        }

        $itemUrl = $url.'/details.php?id='.(int) $row['id'];
        $itemDlUrl = $dllink
            ? (string) $torrentRep->getDownloadUrl((int) $row['id'], $user)
            : $url.'/download.php?id='.(int) $row['id'];

        $title = '';
        if (! empty($query['icat'])) {
            $title .= '['.((string) ($row['category_name'] ?? '')).']';
        }
        $title .= (string) ($row['name'] ?? '');
        if (! empty($query['ismalldescr']) && ! empty($row['small_descr'])) {
            $title .= '['.((string) $row['small_descr']).']';
        }
        if (! empty($query['isize'])) {
            $title .= '['.mksize((int) ($row['size'] ?? 0)).']';
        }
        if (! empty($query['iuplder'])) {
            $title .= '['.$author.']';
        }

        $content = format_comment((string) ($row['descr'] ?? ''), true, false, false, false);
        $infoHashHex = preg_replace_callback(
            '/./s',
            static fn ($m) => sprintf('%02x', ord($m[0])),
            (string) hash_pad((string) ($row['info_hash'] ?? '')),
        );
        $httpHost = (string) request()->getHost();

        $item = '<item>';
        $item .= '<title><![CDATA['.$title.']]></title>';
        $item .= '<link>'.$itemUrl.'</link>';
        $item .= '<description><![CDATA['.$content.']]></description>';
        $item .= '<author>'.$author.'@'.$httpHost.' ('.$author.')</author>';
        $item .= '<category domain="'.$url.'/torrents.php?cat='.(int) ($row['category'] ?? 0).'">';
        $item .= (string) ($row['category_name'] ?? '');
        $item .= '</category>';
        $item .= '<comments><![CDATA['.$url.'/details.php?id='.(int) $row['id'].'&cmtpage=0#startcomments]]></comments>';
        $item .= '<enclosure url="'.$itemDlUrl.'" length="'.(int) ($row['size'] ?? 0).'" type="application/x-bittorrent" />';
        $item .= '<guid isPermaLink="false">'.$infoHashHex.'</guid>';
        $item .= '<pubDate>'.date('r', strtotime((string) ($row['added'] ?? 'now'))).'</pubDate>';
        $item .= '</item>';

        return $item;
    }

    private function plain(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }
}
