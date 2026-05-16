<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Support\BbcodeRenderer;
use App\Support\Codec;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
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
            ->with(['basic_category', 'user'])
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

        return null;
    }

    public function render(): View
    {
        $viewerId = (int) (auth('nexus-web')->id() ?? 0);

        return view('livewire.torrent-detail', [
            'torrent' => $this->torrent,
            'owner' => $this->owner,
            'banReason' => $this->banReason,
            'promotionBadge' => $this->promotionBadge(),
            'taxonomy' => $this->taxonomyRows(),
            'files' => $this->loadFiles(),
            'peerGroups' => $this->loadPeerGroups($viewerId),
            'hotMeter' => $this->hotMeterRows(),
            'descriptionHtml' => $this->descriptionHtml($viewerId),
            'technicalInfoHtml' => $this->technicalInfoHtml(),
            'nfoBlock' => $this->nfoBlock($viewerId),
            'viewerId' => $viewerId,
        ])->layout('layouts.livewire-app', [
            'title' => $this->torrent?->name ?? 'Torrent',
        ]);
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

        if (! function_exists('get_setting') || get_setting('main.enable_technical_info') !== 'yes') {
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
     *     `code_new()` proxy delegates to.
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

        return [
            'html' => Codec::ibm437ToEntities($nfo, $view),
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
        if (! function_exists('get_setting')) {
            return $default;
        }

        $value = get_setting('torrent.nfo_view_style_default', $default);

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
}
