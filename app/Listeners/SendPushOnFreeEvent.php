<?php

namespace App\Listeners;

use App\Events\TorrentPromotionChanged;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

/**
 * On a *global* freeleech event (TorrentPromotionChanged with
 * $global=true), push a notification to every user who:
 *   1. has at least one registered push subscription, AND
 *   2. opted in via User::acceptNotification('free_event').
 *
 * Per-torrent promotion changes ($global=false) are intentionally
 * ignored — they fire many times per day from admin panels and
 * would be too noisy. Reverb still updates the in-page badge live
 * via TorrentPromotionChanged for those.
 *
 * "Normal" resets (sp_state=1) are also skipped — going back to
 * baseline isn't actionable for the user.
 *
 * Queued so a slow VAPID provider never blocks the freeleech form
 * submit. The handler chunks subscribers (1000 at a time) so very
 * large communities don't OOM.
 */
class SendPushOnFreeEvent implements ShouldQueue
{
    public string $queue = 'default';

    /** sp_state → human label used in the push title. */
    private const STATE_LABELS = [
        2 => 'Free',
        3 => '2× upload',
        4 => '2× upload + Free',
        5 => 'Half download',
        6 => '2× upload + Half download',
    ];

    public function __construct(private readonly PushDispatcher $push) {}

    public function handle(TorrentPromotionChanged $event): void
    {
        if (! $event->global) {
            return;
        }
        if (! $this->push->isConfigured()) {
            return;
        }

        $label = self::STATE_LABELS[$event->spState] ?? null;
        if ($label === null) {
            // sp_state=1 (normal) or unknown: skip.
            return;
        }

        $title = "Site-wide event: {$label}";
        $body = "All torrents are now {$label}. Happy seeding!";

        PushSubscription::query()
            ->select('userid')
            ->distinct()
            ->orderBy('userid')
            ->chunk(1000, function (Collection $rows) use ($title, $body, $event): void {
                $userIds = $rows
                    ->pluck('userid')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->all();
                if ($userIds === []) {
                    return;
                }

                $optedIn = User::query()
                    ->whereIn('id', $userIds)
                    ->get(['id', 'notifs'])
                    ->filter(fn (User $user): bool => $user->acceptNotification('free_event'))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
                if ($optedIn === []) {
                    return;
                }

                $this->push->sendToUsers(
                    $optedIn,
                    $title,
                    $body,
                    '/torrents',
                    ['kind' => 'free_event', 'spState' => $event->spState],
                );
            });
    }
}
