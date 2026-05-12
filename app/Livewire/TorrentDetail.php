<?php

namespace App\Livewire;

use App\Models\Torrent;
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
 * Visibility model (intentionally narrower than legacy):
 *
 *   - missing torrent  → 404
 *   - `visible = 'no'` → 404, unless the viewer owns the row
 *   - `banned  = 'yes'` → 404, unless the viewer owns the row
 *
 * The full legacy `can_access_torrent()` / `permissiondenied()` matrix
 * lives in `public/details.php` and is left to the escape-hatch link
 * until parity work catches up.
 */
class TorrentDetail extends Component
{
    public int $torrentId = 0;

    public ?Torrent $torrent = null;

    public ?User $owner = null;

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

        if ($torrent->banned === Torrent::BANNED_YES && ! $isOwner) {
            abort(404);
        }

        $this->torrent = $torrent;
        $this->owner = $torrent->user;

        return null;
    }

    public function render(): View
    {
        return view('livewire.torrent-detail', [
            'torrent' => $this->torrent,
            'owner' => $this->owner,
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
}
