<?php

namespace App\Livewire;

use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
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
        return view('livewire.torrent-detail', [
            'torrent' => $this->torrent,
            'owner' => $this->owner,
            'banReason' => $this->banReason,
            'promotionBadge' => $this->promotionBadge(),
        ])->layout('layouts.livewire-app', [
            'title' => $this->torrent?->name ?? 'Torrent',
        ]);
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
}
