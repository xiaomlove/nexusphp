<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

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
 *   - peer / snatch / file-list tabs
 *   - IMDb / pt-gen rich hero
 *   - bookmark + thanks + vote + report buttons (currently fall back
 *     to the legacy detail page through the "Full legacy view" link).
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
            'viewerId' => $viewerId,
        ])->layout('layouts.livewire-app', [
            'title' => $this->torrent?->name ?? 'Torrent',
        ]);
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
