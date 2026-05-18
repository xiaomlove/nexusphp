<?php

namespace App\Livewire;

use App\Http\Controllers\Legacy\BookmarkController;
use App\Http\Controllers\Legacy\ThanksController;
use App\Models\Claim;
use App\Models\Comment;
use App\Models\CommentEdit;
use App\Models\DownloadSpeed;
use App\Models\File;
use App\Models\Isp;
use App\Models\Peer;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\TorrentOperationLog;
use App\Models\UploadSpeed;
use App\Models\User;
use App\Repositories\ClaimRepository;
use App\Repositories\SearchRepository;
use App\Repositories\TagRepository;
use App\Support\BbcodeRenderer;
use App\Support\Codec;
use App\Support\Imdb;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;
use Nexus\Torrent\BdInfoExtra;
use Nexus\Torrent\TechnicalInformation;

/**
 * `/torrent/{id}` — Modern UI shell for the legacy `public/details.php`
 * page (Strangler Fig A3). This first PR exposes a minimal, Modern UI
 * rendering of the core torrent metadata using the design-system
 * components introduced in PR #140 (`<x-ui.*>`):
 *
 *   - title + small-description (page-header)
 *   - category, promotion, H&R, banned badges (badge)
 *   - size / seeders / leechers / snatched stats (stat)
 *   - uploader, added timestamp, visibility (stat)
 *   - actions: download link, view full legacy page
 *
 * Out of scope (planned for subsequent A3.x PRs):
 *
 *   - comments listing + posting
 *   - snatched list tab
 *   - IMDb / pt-gen rich hero
 *   - bookmark + thanks + vote + report buttons (currently fall back
 *     to the legacy detail page through the "Full legacy view" link).
 *
 * Subsequent A3.x PRs already shipped on top of this one expand the
 * page with file list, peers, taxonomy table, hot meter, full
 * description (rendered through {@see BbcodeRenderer}). The legacy
 * `users.showdescription = 'no'` opt-out is honoured.
 *
 * `?legacy=1` is the canary rollback flag, mirroring `TorrentBrowse`.
 * When set, the component redirects to `/details.php?id={id}&legacy=1`
 * so the legacy detail page is reachable with one keystroke.
 *
 * Visibility model — parity with `public/details.php`:
 *
 *   - missing torrent                                          → 404
 *   - `visible = 'no'` and viewer is not the owner             → 404
 *     (legacy is silent on this; we keep the tighter check to
 *     avoid regressing hidden-torrent privacy.)
 *   - `banned  = 'yes'`:
 *       owner                                                  → 200
 *       viewer has `seebanned` permission                      → 200
 *       otherwise                                              → 403
 *   - `can_access_torrent()` denies access (special category)
 *     and viewer is not the owner                              → 403
 *
 * `App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY` ties a
 * staff comment to a torrent with `approval_status = APPROVAL_STATUS_DENY`.
 * `TorrentDetail` surfaces the most recent such comment as a danger
 * banner above the hero so the uploader sees the rejection reason at
 * the canonical detail URL (legacy renders the same banner inline at
 * `public/details.php:73`).
 */
class TorrentDetail extends Component
{
    public int $torrentId = 0;

    public ?Torrent $torrent = null;

    public ?User $owner = null;

    /**
     * Most recent `approval_deny` operation log for the current torrent
     * when `approval_status` is `APPROVAL_STATUS_DENY`. `null` otherwise.
     */
    public ?TorrentOperationLog $banReason = null;

    public string $newCommentBody = '';

    public ?int $editingCommentId = null;

    public string $editingBody = '';

    public ?string $claimFlash = null;

    public ?string $postWriteBanner = null;

    public ?string $returnto = null;

    private const COMMENT_FLOOD_SECONDS = 10;

    private const POST_WRITE_BANNERS = ['uploaded', 'edited', 'existed'];

    public function mount(int $id): mixed
    {
        if (request()->query('legacy') === '1') {
            return redirect('/details.php?id='.$id.'&legacy=1');
        }

        $this->torrentId = $id;

        // Eager-load the relations that PHPStan can resolve via explicit
        // return types on the model. The seven `basic_*` taxonomy relations
        // (source / medium / codec / standard / processing / team /
        // audio codec) are loaded lazily on access in `taxonomyRows()` —
        // they are not yet typed on the legacy `Torrent` model and we do
        // not want to spread that change across PRs.
        $torrent = Torrent::query()
            ->with(['basic_category', 'user', 'torrent_tags'])
            ->find($id);

        if ($torrent === null) {
            abort(404);
        }

        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        $isOwner = $viewerId !== 0 && $viewerId === (int) $torrent->owner;

        if ($torrent->visible === Torrent::VISIBLE_NO && ! $isOwner) {
            abort(404);
        }

        if ($torrent->banned === Torrent::BANNED_YES && ! $isOwner && ! $this->viewerCan('seebanned', $viewerId)) {
            abort(403);
        }

        if (! $isOwner && ! $this->viewerCanAccessTorrent($torrent, $viewerId)) {
            abort(403);
        }

        $this->torrent = $torrent;
        $this->owner = $torrent->user;
        $this->banReason = $this->loadBanReason($torrent);

        if (request()->filled('hit')) {
            Torrent::where('id', $id)->increment('views');
        }

        foreach (self::POST_WRITE_BANNERS as $bannerKey) {
            if ((string) request()->query($bannerKey, '') !== '') {
                $this->postWriteBanner = $bannerKey;
                break;
            }
        }
        $returnto = (string) request()->query('returnto', '');
        if ($returnto !== '') {
            $this->returnto = $returnto;
        }

        return null;
    }

    public function render(): View
    {
        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        $thanksList = $this->thanksList();

        return view('livewire.torrent-detail', [
            'torrent' => $this->torrent,
            'owner' => $this->owner,
            'banReason' => $this->banReason,
            'postWriteBanner' => $this->postWriteBanner,
            'returnto' => $this->returnto,
            'promotionBadge' => $this->promotionBadge(),
            'promotionSubtext' => $this->promotionSubtext(),
            'tagsHtml' => $this->tagsHtml(),
            'uploaderBandwidth' => $this->uploaderBandwidth(),
            'taxonomy' => $this->taxonomyRows(),
            'files' => $this->loadFiles(),
            'peerGroups' => $this->loadPeerGroups($viewerId),
            'snatches' => $this->loadSnatches($viewerId),
            'comments' => $this->loadComments($viewerId),
            'canPostComment' => $this->canPostComment($viewerId),
            'commentCooldownSeconds' => $this->commentCooldownSeconds($viewerId),
            'viewerCanCommanage' => $this->viewerCan('commanage', $viewerId),
            'hotMeter' => $this->hotMeterRows(),
            'descriptionHtml' => $this->descriptionHtml($viewerId),
            'technicalInfoHtml' => $this->technicalInfoHtml(),
            'customFieldsHtml' => $this->customFieldsHtml(),
            'otherCopies' => $this->otherCopies(),
            'nfoBlock' => $this->nfoBlock($viewerId),
            'viewerId' => $viewerId,
            'isAuthed' => $viewerId > 0,
            'isBookmarked' => $this->isBookmarked($viewerId),
            'hasThanked' => $thanksList['hasThanked'],
            'thanksRecent' => $thanksList['recent'],
            'thanksTotal' => $thanksList['total'],
            'actionRow' => $this->actionRowItems($viewerId),
            'claimBlock' => $this->claimBlock($viewerId),
        ])->layout('layouts.livewire-app', [
            'title' => $this->torrent?->name ?? 'Torrent',
        ]);
    }

    /**
     * Mirrors the visibility rules of `public/details.php:253-295`.
     * Owner auto-promotion of `downloadpos` follows lines 204-205 of
     * the same file. Approval (Layer.js iframe) and claim (separate
     * AJAX block) are out of scope and stay on legacy behind
     * `?legacy=1`.
     *
     * @return list<array{id:string,label:string,title:string,url:string,variant:string}>
     */
    private function actionRowItems(int $viewerId): array
    {
        if ($this->torrent === null || $viewerId <= 0) {
            return [];
        }

        $torrent = $this->torrent;
        $torrentId = (int) $torrent->id;
        $isOwner = $viewerId === (int) $torrent->owner;
        $items = [];

        if ($isOwner || $this->viewerDownloadpos($viewerId) !== 'no') {
            $items[] = [
                'id' => 'download',
                'label' => 'Download .torrent',
                'title' => 'Download this torrent',
                'url' => '/download.php?id='.$torrentId,
                'variant' => 'primary',
            ];
        }

        $canManage = $this->viewerCan('torrentmanage', $viewerId);
        if ($isOwner || $canManage) {
            $editUrl = '/edit.php?id='.$torrentId;
            if ($this->returnto !== null) {
                $editUrl .= '&returnto='.rawurlencode($this->returnto);
            }
            $items[] = [
                'id' => 'edit',
                'label' => $canManage ? 'Edit / delete' : 'Edit',
                'title' => 'Click to edit or delete this torrent',
                'url' => $editUrl,
                'variant' => 'secondary',
            ];
        }

        if ($this->viewerCan('askreseed', $viewerId) && (int) $torrent->seeders === 0) {
            $items[] = [
                'id' => 'reseed',
                'label' => 'Ask for a reseed',
                'title' => 'Ask snatched users for reseeding when there\'s no seeder',
                'url' => '/takereseed.php?reseedid='.$torrentId,
                'variant' => 'secondary',
            ];
        }

        $items[] = [
            'id' => 'report',
            'label' => 'Report torrent',
            'title' => 'Report torrent for violating rules',
            'url' => '/report.php?torrent='.$torrentId,
            'variant' => 'danger',
        ];

        return $items;
    }

    /**
     * Look up the viewer's `users.downloadpos` enum, returning the raw
     * string so the caller can apply the legacy semantics (`!= 'no'`
     * means "may download"). The column default is `'yes'` per the
     * schema, so a missing column / missing row defaults open — never
     * accidentally narrows download access.
     */
    private function viewerDownloadpos(int $viewerId): string
    {
        if ($viewerId <= 0) {
            return 'yes';
        }

        /** @var User|null $viewer */
        $viewer = User::query()->find($viewerId);
        $value = $viewer?->getAttribute('downloadpos');

        return $value === 'no' ? 'no' : 'yes';
    }

    /**
     * @return array{torrentId:int,hasClaimed:bool,claimCount:int,remainingSlots:int,maxPerTorrent:int,detailsUrl:string}|null
     */
    private function claimBlock(int $viewerId): ?array
    {
        if ($this->torrent === null || $viewerId <= 0) {
            return null;
        }

        if (Setting::getByName('torrent.claim_enabled', 'no') != 'yes') {
            return null;
        }

        $added = $this->torrent->added;
        if (! $added instanceof Carbon) {
            return null;
        }

        $ttlDays = (int) Setting::getByName('torrent.claim_torrent_ttl', Claim::TORRENT_TTL);
        if ($added->copy()->addDays($ttlDays)->isAfter(Carbon::now())) {
            return null;
        }

        $torrentId = (int) $this->torrent->id;
        $maxPerTorrent = (int) Setting::getByName('torrent.claim_torrent_user_counts_up_limit', Claim::USER_UP_LIMIT);
        $claimCount = (int) Claim::query()->where('torrent_id', $torrentId)->count();
        $hasClaimed = Claim::query()
            ->where('torrent_id', $torrentId)
            ->where('uid', $viewerId)
            ->exists();

        return [
            'torrentId' => $torrentId,
            'hasClaimed' => $hasClaimed,
            'claimCount' => $claimCount,
            'remainingSlots' => max(0, $maxPerTorrent - $claimCount),
            'maxPerTorrent' => $maxPerTorrent,
            'detailsUrl' => '/claim.php?torrent_id='.$torrentId,
        ];
    }

    public function addClaim(): void
    {
        $this->claimFlash = null;

        if ($this->torrent === null) {
            return;
        }

        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        if ($viewerId <= 0) {
            return;
        }

        try {
            app(ClaimRepository::class)->store($viewerId, (int) $this->torrent->id);
        } catch (\Throwable $e) {
            $this->addError('claim', $e->getMessage());

            return;
        }

        $this->claimFlash = 'Claim recorded.';
    }

    /**
     * Toggle the current viewer's bookmark row for this torrent. The
     * implementation is a near-line-for-line copy of the production
     * legacy {@see BookmarkController}
     * (which `public/bookmark.php` was replaced with in Phase 2) — we
     * keep the SearchRepository call and the per-user cache key so the
     * `bookmark_array` cache and Elasticsearch index stay coherent
     * across both the legacy and Modern UI surfaces.
     *
     * Guests fall through silently — the Blade view does not render
     * the button for an anonymous viewer, but we re-check `$viewerId`
     * here to keep the action safe even if the front-end is bypassed.
     */
    public function toggleBookmark(): void
    {
        if ($this->torrent === null) {
            return;
        }

        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        if ($viewerId <= 0) {
            return;
        }

        $torrentId = (int) $this->torrent->id;

        $existing = NexusDB::table('bookmarks')
            ->where('torrentid', $torrentId)
            ->where('userid', $viewerId)
            ->first();

        $repository = app(SearchRepository::class);

        if ($existing !== null) {
            $existing = (array) $existing;
            $repository->deleteBookmark((int) $existing['id']);
            NexusDB::table('bookmarks')
                ->where('torrentid', $torrentId)
                ->where('userid', $viewerId)
                ->delete();
            NexusDB::cache_del('user_'.$viewerId.'_bookmark_array');

            return;
        }

        $newId = (int) NexusDB::insert('bookmarks', [
            'torrentid' => $torrentId,
            'userid' => $viewerId,
        ]);
        NexusDB::cache_del('user_'.$viewerId.'_bookmark_array');
        $repository->addBookmark($newId);
    }

    /**
     * Record the current viewer's "thanks" for this torrent and credit
     * the seedbonus on both sides. Mirrors the contract pinned by
     * {@see ThanksController}:
     *
     *   - Guests are silently ignored (the button is not rendered for
     *     anonymous viewers, but the action defends in depth).
     *   - Already-thanked viewers are a no-op (the UNIQUE KEY on the
     *     `thanks` table would 1062 otherwise).
     *   - Bonus crediting is gated on the `tweak.bonus` setting, with
     *     the same `enable` / `disablesave` set the controller honours.
     *   - Settings are read through {@see Setting::getByName()} rather
     *     than `get_setting()` — the latter caches the whole settings
     *     tree in a static var, which prevents test-time mutations
     *     from being visible. See the matching PR-2 comment on
     *     {@see TorrentDetail::technicalInfoHtml()}.
     */
    public function sayThanks(): void
    {
        if ($this->torrent === null) {
            return;
        }

        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        if ($viewerId <= 0) {
            return;
        }

        $torrentId = (int) $this->torrent->id;

        $alreadyThanked = NexusDB::table('thanks')
            ->where('torrentid', $torrentId)
            ->where('userid', $viewerId)
            ->exists();
        if ($alreadyThanked) {
            return;
        }

        NexusDB::table('thanks')->insert([
            'torrentid' => $torrentId,
            'userid' => $viewerId,
        ]);

        $tweak = (string) Setting::getByName('tweak.bonus', 'enable');
        if ($tweak !== 'enable' && $tweak !== 'disablesave') {
            return;
        }

        $sayBonus = (float) Setting::getByName('bonus.saythanks', 0);
        if ($sayBonus > 0.0) {
            NexusDB::table('users')
                ->where('id', $viewerId)
                ->update(['seedbonus' => NexusDB::raw('seedbonus + '.$sayBonus)]);
        }

        $receiveBonus = (float) Setting::getByName('bonus.receivethanks', 0);
        $ownerId = (int) $this->torrent->owner;
        if ($ownerId > 0 && $receiveBonus > 0.0) {
            NexusDB::table('users')
                ->where('id', $ownerId)
                ->update(['seedbonus' => NexusDB::raw('seedbonus + '.$receiveBonus)]);
        }
    }

    /**
     * Whether the viewer has bookmarked this torrent. Guests always
     * return `false` so the view renders the "log in to bookmark"
     * variant rather than the toggle button.
     */
    private function isBookmarked(int $viewerId): bool
    {
        if ($viewerId <= 0 || $this->torrent === null) {
            return false;
        }

        return NexusDB::table('bookmarks')
            ->where('torrentid', (int) $this->torrent->id)
            ->where('userid', $viewerId)
            ->exists();
    }

    /**
     * Resolve the "thanks-by" panel data: the 20 most recent thanker
     * usernames (matches the legacy `LIMIT 20` query at
     * `details.php:649`), the total count, and whether the current
     * viewer has already thanked.
     *
     * @return array{recent:Collection<int,string>,total:int,hasThanked:bool}
     */
    private function thanksList(): array
    {
        if ($this->torrent === null) {
            return [
                'recent' => new Collection,
                'total' => 0,
                'hasThanked' => false,
            ];
        }

        $torrentId = (int) $this->torrent->id;
        $viewerId = (int) (auth('nexus-web')->id() ?? 0);

        $total = (int) NexusDB::table('thanks')
            ->where('torrentid', $torrentId)
            ->count();

        $hasThanked = $viewerId > 0 && NexusDB::table('thanks')
            ->where('torrentid', $torrentId)
            ->where('userid', $viewerId)
            ->exists();

        /** @var Collection<int,string> $recent */
        $recent = NexusDB::table('thanks')
            ->where('torrentid', $torrentId)
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('userid')
            ->pipe(function (Collection $userIds): Collection {
                if ($userIds->isEmpty()) {
                    /** @var Collection<int,string> $empty */
                    $empty = new Collection;

                    return $empty;
                }

                $usernamesByid = User::query()
                    ->whereIn('id', $userIds->all())
                    ->pluck('username', 'id');

                return $userIds
                    ->map(fn ($id) => (string) ($usernamesByid[(int) $id] ?? ''))
                    ->filter(fn (string $name): bool => $name !== '')
                    ->values();
            });

        return [
            'recent' => $recent,
            'total' => $total,
            'hasThanked' => $hasThanked,
        ];
    }

    /**
     * Render the four "hot meter" cells the legacy details page shows
     * just below the torrent-info block (legacy `details.php:505`):
     *
     *   - views (column: `torrents.views`)
     *   - hits  (column: `torrents.hits`)
     *   - snatched count (column: `torrents.times_completed`)
     *   - last seeder activity (column: `torrents.last_action`)
     *
     * Returns a label → display-value list rather than a raw object so
     * the view does not have to repeat any formatting decisions.
     *
     * @return array<string,string>
     */
    private function hotMeterRows(): array
    {
        if ($this->torrent === null) {
            return [];
        }

        $lastAction = $this->torrent->last_action;
        $lastSeen = $lastAction instanceof Carbon
            ? $lastAction->diffForHumans()
            : '—';

        return [
            'Views' => number_format((int) ($this->torrent->views ?? 0)),
            'Hits' => number_format((int) ($this->torrent->hits ?? 0)),
            'Snatched' => number_format((int) ($this->torrent->times_completed ?? 0)),
            'Last seeder' => $lastSeen,
        ];
    }

    /**
     * Resolve the safe-HTML body of the torrent's full description
     * (legacy column: `torrent_extras.descr`, rendered through
     * `format_comment()` at `details.php:344`). The Modern UI uses
     * {@see BbcodeRenderer::toHtml()} — the dependency-free renderer
     * already used by `TopicView` — instead of the legacy global
     * helper.
     *
     * Legacy parity rules pinned here:
     *
     *   - returns the empty string when the viewer has opted out via
     *     `users.showdescription = 'no'`. Default value `'yes'` keeps
     *     the block visible for everyone else, matching
     *     `details.php:342`.
     *   - returns the empty string when `torrent_extras.descr` is
     *     missing or empty, so the Blade can short-circuit on
     *     `! empty($descriptionHtml)`.
     */
    private function descriptionHtml(int $viewerId): string
    {
        if ($this->torrent === null) {
            return '';
        }

        if ($viewerId > 0 && $this->viewerShowDescription($viewerId) === 'no') {
            return '';
        }

        $extra = $this->loadExtra();
        if ($extra === null) {
            return '';
        }

        $descr = (string) ($extra->descr ?? '');
        if ($descr === '') {
            return '';
        }

        return BbcodeRenderer::toHtml($descr);
    }

    /**
     * Look up the viewer's `users.showdescription` enum. Defaults to
     * `'yes'` when the column is unset / the viewer record is missing
     * — matching the column default in `database/schema/mysql-schema.sql`.
     */
    private function viewerShowDescription(int $viewerId): string
    {
        /** @var User|null $viewer */
        $viewer = User::query()->find($viewerId);
        $value = $viewer?->getAttribute('showdescription');

        return $value === 'no' ? 'no' : 'yes';
    }

    /**
     * Hydrate the `torrent_extras` row (`descr` / `nfo` / `media_info` /
     * `pt_gen`). Returns `null` when no row exists — legacy
     * `details.php` mirrors this by falling back to empty values via
     * the `LEFT JOIN torrent_extras` in its big SELECT.
     */
    private function loadExtra(): ?TorrentExtra
    {
        if ($this->torrent === null) {
            return null;
        }

        return TorrentExtra::query()
            ->where('torrent_id', $this->torrent->id)
            ->first();
    }

    /**
     * Render the "Technical info" block from `torrent_extras.media_info`
     * (legacy `details.php:314-340`). Returns the empty string when the
     * block must be hidden — the Blade can then short-circuit on a
     * single `if ($technicalInfoHtml !== '')` check.
     *
     * Legacy parity rules pinned here:
     *
     *   - hidden when site setting `main.enable_technical_info != 'yes'`
     *     (default `'yes'` in `nexus/Install/settings.default.php`).
     *   - hidden when no `torrent_extras` row exists, or when
     *     `media_info` is the empty string.
     *   - dispatches between BD-info and MediaInfo formats by sniffing
     *     the first non-empty line for `DISC INFO` / `Disc Title` /
     *     `Disc Label`, matching `details.php:319-326`.
     *   - delegates rendering to the existing `\Nexus\Torrent\...`
     *     classes — the legacy details page calls the same
     *     `renderOnDetailsPage()` method, so the HTML is byte-identical.
     */
    private function technicalInfoHtml(): string
    {
        if ($this->torrent === null) {
            return '';
        }

        // Read through `Setting::getByName()` rather than `get_setting()`
        // — the latter caches the entire settings tree in a static var,
        // so a runtime toggle (or a test seeding a row) is not picked up
        // until the cache is rebuilt. `Setting::getByName()` is a tiny
        // single-row Eloquent query and matches the read pattern used
        // by other refactored controllers (see `AdRedirectController`).
        $enabled = (string) Setting::getByName('main.enable_technical_info', 'yes');
        if ($enabled !== 'yes') {
            return '';
        }

        $extra = $this->loadExtra();
        if ($extra === null) {
            return '';
        }

        $mediaInfo = (string) ($extra->media_info ?? '');
        if ($mediaInfo === '') {
            return '';
        }

        if ($this->isBdInfoBlob($mediaInfo)) {
            return (string) (new BdInfoExtra($mediaInfo))->renderOnDetailsPage();
        }

        return (string) (new TechnicalInformation($mediaInfo))->renderOnDetailsPage();
    }

    /**
     * Sniff whether the technical-info payload is a Blu-ray BD-info dump
     * (which `BdInfoExtra` knows how to parse) versus a MediaInfo dump.
     * Mirrors `details.php:319-326`.
     */
    private function isBdInfoBlob(string $mediaInfo): bool
    {
        $firstLine = (string) strtok($mediaInfo, "\n");

        return str_contains($firstLine, 'DISC INFO')
            || str_contains($firstLine, 'Disc Title')
            || str_contains($firstLine, 'Disc Label');
    }

    /**
     * Render the "NFO" block from `torrent_extras.nfo`
     * (legacy `details.php:350-356`). Returns `null` when the block must
     * be hidden so the Blade short-circuits on a single `if ($nfoBlock)`
     * check.
     *
     * Legacy parity rules pinned here:
     *
     *   - hidden when the viewer lacks the `viewnfo` permission.
     *   - hidden when the viewer opted out via
     *     `users.shownfo = 'no'` (legacy default: `'yes'`).
     *   - hidden when `torrent_extras.nfo` is missing or empty
     *     (legacy guard: `nfosz > 0`).
     *   - decodes the IBM-437 blob through
     *     {@see Codec::ibm437ToEntities()} — the same helper the legacy
     *     `code_new()` proxy delegates to. The ASCII portion is run
     *     through `htmlspecialchars()` *before* the byte-walk so embedded
     *     HTML (e.g. `<script>...</script>`) is rendered as text instead
     *     of being executed. The legacy `code()` does the same escape;
     *     `code_new()` was added later for a faster byte-walk and (by
     *     accident) dropped the escape — the Modern UI fixes that.
     *   - the view style defaults to `torrent.nfo_view_style_default`
     *     and falls back to {@see Torrent::NFO_VIEW_STYLE_DOS}.
     *
     * @return array{html:string,view:string}|null
     */
    private function nfoBlock(int $viewerId): ?array
    {
        if ($this->torrent === null) {
            return null;
        }

        if (! $this->viewerCan('viewnfo', $viewerId)) {
            return null;
        }

        if ($this->viewerShowNfo($viewerId) === 'no') {
            return null;
        }

        $extra = $this->loadExtra();
        if ($extra === null) {
            return null;
        }

        $nfo = (string) ($extra->nfo ?? '');
        if ($nfo === '') {
            return null;
        }

        $view = $this->nfoViewStyle();

        // Escape ASCII HTML metacharacters before the byte-walk so the
        // raw `<` / `>` / `&` / `"` bytes that survive the IBM-437 decode
        // cannot break out of the `<pre>` wrapper in the Blade view.
        // High bytes (>= 0x7F) are still turned into numeric entities
        // by `Codec::ibm437ToEntities()`.
        $safe = htmlspecialchars($nfo, ENT_QUOTES, 'UTF-8');

        return [
            'html' => Codec::ibm437ToEntities($safe, $view),
            'view' => $view,
        ];
    }

    /**
     * Look up the viewer's `users.shownfo` enum. Defaults to `'yes'`
     * when the column is unset / the viewer record is missing — matches
     * the column default in `database/schema/mysql-schema.sql`.
     */
    private function viewerShowNfo(int $viewerId): string
    {
        if ($viewerId <= 0) {
            return 'yes';
        }

        /** @var User|null $viewer */
        $viewer = User::query()->find($viewerId);
        $value = $viewer?->getAttribute('shownfo');

        return $value === 'no' ? 'no' : 'yes';
    }

    /**
     * Resolve the configured default NFO view style. Falls back to
     * `Torrent::NFO_VIEW_STYLE_DOS` when no setting is registered —
     * matching `nexus/Install/settings.default.php`.
     */
    private function nfoViewStyle(): string
    {
        $default = Torrent::NFO_VIEW_STYLE_DOS;
        $value = Setting::getByName('torrent.nfo_view_style_default', $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Resolve the seven taxonomy fields shown on `public/details.php`
     * (source / medium / codec / standard / processing / team / audio
     * codec) into a label-keyed list of strings. Only fields with a
     * resolved name are returned — mirroring the legacy behaviour of
     * silently skipping empty taxonomy slots.
     *
     * @return array<string,string>
     */
    private function taxonomyRows(): array
    {
        if ($this->torrent === null) {
            return [];
        }

        $candidates = [
            'Source' => $this->torrent->basic_source?->name,
            'Medium' => $this->torrent->basic_medium?->name,
            'Codec' => $this->torrent->basic_codec?->name,
            'Standard' => $this->torrent->basic_standard?->name,
            'Processing' => $this->torrent->basic_processing?->name,
            'Team' => $this->torrent->basic_team?->name,
            'Audio codec' => $this->torrent->basic_audiocodec?->name,
        ];

        return array_filter($candidates, fn ($name) => ! empty($name));
    }

    /**
     * Map the legacy `sp_state` enum onto a (label, ui-variant) tuple
     * consumable by `<x-ui.badge>`. Returns `null` for the default
     * "normal" state so the view can skip the badge.
     *
     * @return array{0:string,1:string}|null
     */
    private function promotionBadge(): ?array
    {
        if ($this->torrent === null) {
            return null;
        }

        $spState = (int) ($this->torrent->getRawOriginal('sp_state') ?? Torrent::PROMOTION_NORMAL);

        return match ($spState) {
            Torrent::PROMOTION_FREE => ['Free', 'success'],
            Torrent::PROMOTION_TWO_TIMES_UP => ['2× Up', 'primary'],
            Torrent::PROMOTION_FREE_TWO_TIMES_UP => ['Free / 2× Up', 'success'],
            Torrent::PROMOTION_HALF_DOWN => ['50%', 'warning'],
            Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP => ['50% / 2× Up', 'warning'],
            Torrent::PROMOTION_ONE_THIRD_DOWN => ['30%', 'warning'],
            default => null,
        };
    }

    private function promotionSubtext(): ?string
    {
        if ($this->torrent === null) {
            return null;
        }

        $spState = (int) ($this->torrent->getRawOriginal('sp_state') ?? Torrent::PROMOTION_NORMAL);
        if ($spState === Torrent::PROMOTION_NORMAL) {
            return null;
        }

        $timeType = (int) ($this->torrent->getRawOriginal('promotion_time_type') ?? Torrent::PROMOTION_TIME_TYPE_GLOBAL);
        $until = $this->torrent->promotion_until;

        return match ($timeType) {
            Torrent::PROMOTION_TIME_TYPE_PERMANENT => 'Permanent',
            Torrent::PROMOTION_TIME_TYPE_DEADLINE => $until instanceof Carbon
                ? 'Until '.$until->format('Y-m-d H:i')
                : null,
            default => null,
        };
    }

    private function tagsHtml(): string
    {
        if ($this->torrent === null) {
            return '';
        }

        $tagIds = $this->torrent->torrent_tags->pluck('tag_id')->all();
        if ($tagIds === []) {
            return '';
        }

        $searchBoxId = (int) ($this->torrent->basic_category?->mode ?? 0);

        return (new TagRepository)->renderSpan($searchBoxId, $tagIds);
    }

    /**
     * @return array{isp:?string,up:?string,down:?string}|null
     */
    private function uploaderBandwidth(): ?array
    {
        if ($this->torrent === null || $this->owner === null) {
            return null;
        }

        $uploadId = (int) ($this->owner->getRawOriginal('upload') ?? 0);
        $downloadId = (int) ($this->owner->getRawOriginal('download') ?? 0);
        $ispId = (int) ($this->owner->getRawOriginal('isp') ?? 0);

        if ($uploadId === 0 && $downloadId === 0 && $ispId === 0) {
            return null;
        }

        $up = $uploadId > 0 ? UploadSpeed::query()->find($uploadId)?->name : null;
        $down = $downloadId > 0 ? DownloadSpeed::query()->find($downloadId)?->name : null;
        $isp = $ispId > 0 ? Isp::query()->find($ispId)?->name : null;

        if ($up === null && $down === null && $isp === null) {
            return null;
        }

        return [
            'isp' => $isp,
            'up' => $up,
            'down' => $down,
        ];
    }

    private function customFieldsHtml(): string
    {
        if ($this->torrent === null) {
            return '';
        }

        $searchBoxId = (int) ($this->torrent->basic_category?->mode ?? 0);
        if ($searchBoxId === 0) {
            return '';
        }

        $torrentId = (int) $this->torrent->id;

        if (! isset($GLOBALS['Cache']) || $GLOBALS['Cache'] === null) {
            $GLOBALS['Cache'] = new \class_cache_redis;
        }

        ob_start();
        try {
            $returned = (new Field)->renderOnTorrentDetailsPage($torrentId, $searchBoxId);
        } finally {
            $printed = (string) ob_get_clean();
        }

        return trim($printed.(string) $returned);
    }

    /**
     * @return Collection<int,Torrent>
     */
    private function otherCopies(): Collection
    {
        if ($this->torrent === null) {
            return new Collection;
        }

        $imdbId = Imdb::parseId((string) ($this->torrent->getRawOriginal('url') ?? ''));
        if ($imdbId === null) {
            return new Collection;
        }

        $torrentId = (int) $this->torrent->id;

        return Torrent::query()
            ->with('basic_category')
            ->where('url', $imdbId)
            ->where('id', '!=', $torrentId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /**
     * Bridge to the global legacy `user_can()` permission helper. Guests
     * (uid 0) always fail. When the helper is missing — e.g. someone
     * boots the component outside the legacy autoload — we err on the
     * side of denying so the access matrix doesn't silently widen.
     */
    private function viewerCan(string $permission, int $uid): bool
    {
        if ($uid <= 0 || ! function_exists('user_can')) {
            return false;
        }

        return (bool) user_can($permission, false, $uid);
    }

    /**
     * Bridge to the legacy `can_access_torrent()` helper that gates
     * the "special category" (`main.spsct = yes`) flag. Defaults to
     * "allowed" if the helper is missing, matching the legacy short-
     * circuit when `main.spsct != 'yes'`.
     */
    private function viewerCanAccessTorrent(Torrent $torrent, int $uid): bool
    {
        if (! function_exists('can_access_torrent')) {
            return true;
        }

        $payload = [
            'id' => (int) $torrent->id,
            'search_box_id' => (int) ($torrent->basic_category->mode ?? 0),
        ];

        return (bool) can_access_torrent($payload, $uid);
    }

    /**
     * Pull the most recent `approval_deny` log row for a torrent whose
     * `approval_status` is `APPROVAL_STATUS_DENY`. Returns `null` for
     * every other state, mirroring the legacy banner condition.
     */
    private function loadBanReason(Torrent $torrent): ?TorrentOperationLog
    {
        if ((int) $torrent->approval_status !== Torrent::APPROVAL_STATUS_DENY) {
            return null;
        }

        return TorrentOperationLog::query()
            ->where('torrent_id', $torrent->id)
            ->where('action_type', TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve the `.torrent` file list rows shown on `public/viewfilelist.php`.
     * Returns an empty collection when the torrent has no `files` rows,
     * mirroring the legacy short-circuit at the top of the helper page.
     *
     * The list is intentionally NOT eager-loaded in `mount()`: many torrents
     * have hundreds of rows and the legacy page also lazy-loaded them behind a
     * "see full list" toggle. Re-querying on each render is acceptable for the
     * read-only view; caching can be layered in later.
     *
     * @return Collection<int,File>
     */
    private function loadFiles(): Collection
    {
        if ($this->torrent === null) {
            return new Collection;
        }

        /** @var Collection<int,File> $rows */
        $rows = File::query()
            ->where('torrent', $this->torrent->id)
            ->orderBy('id')
            ->get();

        return $rows;
    }

    /**
     * Resolve the seeder + leecher rows shown on `public/viewpeerlist.php`
     * and split them into two collections.
     *
     * Each peer is decorated with a `display_username` attribute so the view
     * can render "Anonymous" for users whose `privacy = 'strong'` (or for the
     * torrent uploader when `torrent.anonymous = 'yes'`) without having to
     * repeat the privacy rule per template branch. Viewers with the
     * `viewanonymous` permission — plus the user looking at their own row —
     * always see the real username.
     *
     * @return array{seeders:Collection<int,Peer>,leechers:Collection<int,Peer>}
     */
    private function loadPeerGroups(int $viewerId): array
    {
        if ($this->torrent === null) {
            return ['seeders' => new Collection, 'leechers' => new Collection];
        }

        /** @var Collection<int,Peer> $peers */
        $peers = Peer::query()
            ->with(['user:id,username,privacy'])
            ->where('torrent', $this->torrent->id)
            ->orderByDesc('seeder')
            ->orderBy('id')
            ->get();

        $canViewAnonymous = $this->viewerCan('viewanonymous', $viewerId);
        $ownerId = (int) $this->torrent->owner;
        $torrentIsAnonymous = $this->torrent->anonymous === 'yes';

        $peers->each(function (Peer $peer) use ($canViewAnonymous, $ownerId, $torrentIsAnonymous, $viewerId): void {
            $peerUserId = (int) $peer->userid;
            $isOwnRow = $viewerId !== 0 && $viewerId === $peerUserId;
            $isStrongPrivacy = ($peer->user?->privacy === 'strong')
                || ($torrentIsAnonymous && $peerUserId === $ownerId);

            if ($isStrongPrivacy && ! $canViewAnonymous && ! $isOwnRow) {
                $peer->setAttribute('display_username', null);
            } else {
                $peer->setAttribute('display_username', $peer->user?->username);
            }
        });

        return [
            'seeders' => $peers->where('seeder', Peer::SEEDER_YES)->values(),
            'leechers' => $peers->where('seeder', Peer::SEEDER_NO)->values(),
        ];
    }

    public function postComment(): void
    {
        if ($this->torrent === null) {
            return;
        }

        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        if ($viewerId <= 0) {
            return;
        }

        $viewer = User::query()->select(['id', 'parked', 'last_comment'])->find($viewerId);
        if ($viewer === null || $viewer->parked === 'yes') {
            $this->addError('newCommentBody', __('comment.std_your_account_parked', [], 'en'));

            return;
        }

        $remaining = $this->commentCooldownSeconds($viewerId);
        if ($remaining > 0) {
            $this->addError('newCommentBody', __('comment.std_comment_flooding_denied').$remaining);

            return;
        }

        $body = trim($this->newCommentBody);
        if ($body === '') {
            $this->addError('newCommentBody', __('comment.std_comment_body_empty'));

            return;
        }

        $torrentId = (int) $this->torrent->id;
        $now = Carbon::now()->toDateTimeString();

        $newId = (int) NexusDB::table('comments')->insertGetId([
            'user' => $viewerId,
            'torrent' => $torrentId,
            'added' => $now,
            'text' => $body,
            'ori_text' => $body,
            'editedby' => 0,
            'editdate' => null,
            'offer' => 0,
            'request' => 0,
            'anonymous' => 'no',
        ]);

        NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->update(['comments' => NexusDB::raw('comments + 1')]);

        NexusDB::table('users')
            ->where('id', $viewerId)
            ->update(['last_comment' => $now]);

        $tweak = (string) Setting::getByName('tweak.bonus', 'enable');
        if ($tweak === 'enable' || $tweak === 'disablesave') {
            $bonus = (float) Setting::getByName('bonus.addcomment', 0);
            if ($bonus > 0.0) {
                NexusDB::table('users')
                    ->where('id', $viewerId)
                    ->update(['seedbonus' => NexusDB::raw('seedbonus + '.$bonus)]);
            }
        }

        NexusDB::cache_del('torrent_'.$torrentId.'_last_comment_content');

        $this->newCommentBody = '';
        $this->dispatch('comment-posted', commentId: $newId);
    }

    public function startEditComment(int $commentId): void
    {
        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        $comment = $this->fetchOwnTorrentComment($commentId);
        if ($comment === null || ! $this->viewerCanEditComment($comment, $viewerId)) {
            return;
        }

        $this->editingCommentId = $commentId;
        $this->editingBody = (string) $comment->text;
    }

    public function cancelEditComment(): void
    {
        $this->editingCommentId = null;
        $this->editingBody = '';
    }

    public function updateComment(int $commentId): void
    {
        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        $comment = $this->fetchOwnTorrentComment($commentId);
        if ($comment === null || ! $this->viewerCanEditComment($comment, $viewerId)) {
            return;
        }

        $body = trim($this->editingBody);
        if ($body === '') {
            $this->addError('editingBody', __('comment.std_comment_body_empty'));

            return;
        }

        $previousBody = (string) $comment->text;
        $now = Carbon::now()->toDateTimeString();

        if ($previousBody !== $body) {
            CommentEdit::query()->insert([
                'commentid' => $commentId,
                'editor_userid' => $viewerId,
                'body_before' => $previousBody,
                'edited_at' => $now,
            ]);
        }

        NexusDB::table('comments')
            ->where('id', $commentId)
            ->update([
                'text' => $body,
                'editdate' => $now,
                'editedby' => $viewerId,
            ]);

        NexusDB::cache_del('torrent_'.(int) $this->torrent->id.'_last_comment_content');

        $this->cancelEditComment();
    }

    public function deleteComment(int $commentId): void
    {
        $viewerId = (int) (auth('nexus-web')->id() ?? 0);
        if (! $this->viewerCan('commanage', $viewerId)) {
            return;
        }

        $comment = $this->fetchOwnTorrentComment($commentId);
        if ($comment === null) {
            return;
        }

        $authorId = (int) $comment->user;
        $torrentId = (int) $this->torrent->id;

        $deleted = NexusDB::table('comments')->where('id', $commentId)->delete();
        if ($deleted <= 0) {
            return;
        }

        NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->update(['comments' => NexusDB::raw('GREATEST(0, comments - 1)')]);

        $tweak = (string) Setting::getByName('tweak.bonus', 'enable');
        if ($tweak === 'enable' || $tweak === 'disablesave') {
            $bonus = (float) Setting::getByName('bonus.addcomment', 0);
            if ($bonus > 0.0 && $authorId > 0) {
                NexusDB::table('users')
                    ->where('id', $authorId)
                    ->update(['seedbonus' => NexusDB::raw('seedbonus - '.$bonus)]);
            }
        }

        NexusDB::cache_del('torrent_'.$torrentId.'_last_comment_content');

        if ($this->editingCommentId === $commentId) {
            $this->cancelEditComment();
        }
    }

    private function fetchOwnTorrentComment(int $commentId): ?Comment
    {
        if ($this->torrent === null) {
            return null;
        }

        /** @var Comment|null $row */
        $row = Comment::query()
            ->where('id', $commentId)
            ->where('torrent', (int) $this->torrent->id)
            ->first();

        return $row;
    }

    private function viewerCanEditComment(Comment $comment, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        if ((int) $comment->user === $viewerId) {
            return true;
        }

        return $this->viewerCan('commanage', $viewerId);
    }

    private function canPostComment(int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        $viewer = User::query()->select(['parked'])->find($viewerId);

        return $viewer !== null && $viewer->parked !== 'yes';
    }

    private function commentCooldownSeconds(int $viewerId): int
    {
        if ($viewerId <= 0) {
            return 0;
        }

        if ($this->viewerCan('commanage', $viewerId)) {
            return 0;
        }

        $viewer = User::query()->select(['last_comment'])->find($viewerId);
        if ($viewer === null || $viewer->last_comment === null) {
            return 0;
        }

        $elapsed = Carbon::now()->getTimestamp() - $viewer->last_comment->getTimestamp();

        return max(0, self::COMMENT_FLOOD_SECONDS - $elapsed);
    }

    /**
     * @return Collection<int,Snatch>
     */
    private function loadSnatches(int $viewerId): Collection
    {
        if ($this->torrent === null) {
            return new Collection;
        }

        /** @var Collection<int,Snatch> $rows */
        $rows = Snatch::query()
            ->with(['user:id,username,privacy'])
            ->where('torrentid', $this->torrent->id)
            ->where('finished', Snatch::FINISHED_YES)
            ->orderByDesc('completedat')
            ->orderByDesc('id')
            ->get();

        $canViewAnonymous = $this->viewerCan('viewanonymous', $viewerId);

        $rows->each(function (Snatch $snatch) use ($canViewAnonymous, $viewerId): void {
            $snatchUserId = (int) $snatch->userid;
            $isOwnRow = $viewerId !== 0 && $viewerId === $snatchUserId;
            $isStrongPrivacy = $snatch->user?->privacy === 'strong';

            if ($isStrongPrivacy && ! $canViewAnonymous && ! $isOwnRow) {
                $snatch->setAttribute('display_username', null);
            } else {
                $snatch->setAttribute('display_username', $snatch->user?->username);
            }
        });

        return $rows;
    }

    /**
     * @return Collection<int,Comment>
     */
    private function loadComments(int $viewerId): Collection
    {
        if ($this->torrent === null) {
            return new Collection;
        }

        /** @var Collection<int,Comment> $rows */
        $rows = Comment::query()
            ->with(['create_user:id,username,privacy'])
            ->where('torrent', $this->torrent->id)
            ->orderBy('id')
            ->get();

        $canViewAnonymous = $this->viewerCan('viewanonymous', $viewerId);

        $rows->each(function (Comment $comment) use ($canViewAnonymous, $viewerId): void {
            $authorId = (int) $comment->user;
            $isOwnRow = $viewerId !== 0 && $viewerId === $authorId;
            $isStrongPrivacy = $comment->create_user?->privacy === 'strong';
            $isAnonymousComment = $comment->anonymous === 'yes';

            $shouldHide = ($isStrongPrivacy || $isAnonymousComment)
                && ! $canViewAnonymous
                && ! $isOwnRow;

            if ($shouldHide) {
                $comment->setAttribute('display_username', null);
            } else {
                $comment->setAttribute('display_username', $comment->create_user?->username);
            }
        });

        return $rows;
    }
}
