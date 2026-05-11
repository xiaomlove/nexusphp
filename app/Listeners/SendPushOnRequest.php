<?php

namespace App\Listeners;

use App\Events\RequestFulfilled;
use App\Events\RequestSupplied;
use App\Models\User;
use App\Services\PushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Push notifications around the request workflow. Two paths:
 *
 * 1. {@see handleSupplied} — someone supplied a torrent for an open
 *    request. Push the request author (excluding self-supply, where
 *    they wouldn't gain anything from a notification).
 *
 * 2. {@see handleFulfilled} — the request author confirmed the
 *    request as filled. Push every torrent owner that earned bonus,
 *    excluding the request author themselves (in case they uploaded
 *    one of the chosen torrents).
 *
 * Both paths gate on {@see User::acceptNotification('request_filled')}.
 */
class SendPushOnRequest implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly PushDispatcher $push) {}

    public function handleSupplied(RequestSupplied $event): void
    {
        if ($event->requestUserId <= 0 || $event->requestUserId === $event->supplierId) {
            return;
        }
        $user = User::query()->find($event->requestUserId, ['id', 'notifs']);
        if ($user === null || ! $user->acceptNotification('request_filled')) {
            return;
        }

        $name = (string) Str::limit($event->requestName, 80);
        $title = $name !== '' ? "Supply for: {$name}" : 'Supply for your request';
        $body = $name !== ''
            ? "Someone supplied a torrent for your request \"{$name}\". Confirm it to pay the bonus."
            : 'Someone supplied a torrent for your request. Confirm it to pay the bonus.';

        $this->push->sendToUser(
            (int) $user->id,
            $title,
            $body,
            '/viewrequests.php?action=view&id='.$event->requestId,
            ['kind' => 'request_supplied', 'requestId' => $event->requestId, 'torrentId' => $event->torrentId],
        );
    }

    public function handleFulfilled(RequestFulfilled $event): void
    {
        $owners = array_values(array_unique(array_filter(
            $event->ownerUserIds,
            fn (int $id): bool => $id > 0 && $id !== $event->requestUserId,
        )));
        if ($owners === []) {
            return;
        }
        $optedIn = User::query()
            ->whereIn('id', $owners)
            ->get(['id', 'notifs'])
            ->filter(fn (User $u): bool => $u->acceptNotification('request_filled'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        if ($optedIn === []) {
            return;
        }

        $name = (string) Str::limit($event->requestName, 80);
        $title = $name !== '' ? "Request filled: {$name}" : 'Request filled';
        $bonusFragment = $event->bonusEach > 0
            ? ' You earned '.number_format($event->bonusEach, 2).' bonus.'
            : '';
        $body = ($name !== ''
            ? "Your torrent fulfilled the request \"{$name}\"."
            : 'Your torrent fulfilled a request.').$bonusFragment;

        $this->push->sendToUsers(
            $optedIn,
            $title,
            $body,
            '/viewrequests.php?action=view&id='.$event->requestId,
            ['kind' => 'request_filled', 'requestId' => $event->requestId],
        );
    }
}
