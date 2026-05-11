<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Sends Web Push notifications via VAPID. Reads subscriptions from
 * push_subscriptions (PR #85) and dispatches in batches through
 * minishlink/web-push.
 *
 * Failed/expired subscriptions (HTTP 404 / 410 from the push service)
 * are pruned automatically so the table doesn't grow unbounded.
 *
 * If VAPID keys are not configured the dispatcher is a no-op — useful
 * for local dev and during the migration window where push is opt-in.
 */
final class PushDispatcher
{
    private const TTL_SECONDS = 86400;

    /**
     * Send a notification to a single user (across all of their
     * registered devices). Returns the number of subscriptions the
     * payload was successfully delivered to.
     *
     * @param  array<string, mixed>  $extra  Optional `data` payload merged into the SW notification.
     */
    public function sendToUser(int $userId, string $title, string $body, ?string $url = null, array $extra = []): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }
        $subs = PushSubscription::query()->where('userid', $userId)->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        return $this->dispatch($subs->all(), $title, $body, $url, $extra);
    }

    /**
     * Broadcast a notification to a set of users. Useful for free-event
     * announcements (one event → all opted-in users) or staff-only
     * notifications.
     *
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $extra
     */
    public function sendToUsers(array $userIds, string $title, string $body, ?string $url = null, array $extra = []): int
    {
        if (! $this->isConfigured() || $userIds === []) {
            return 0;
        }
        $subs = PushSubscription::query()->whereIn('userid', $userIds)->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        return $this->dispatch($subs->all(), $title, $body, $url, $extra);
    }

    public function isConfigured(): bool
    {
        $public = (string) config('nexus.webpush.vapid_public_key', '');
        $private = (string) config('nexus.webpush.vapid_private_key', '');

        return $public !== '' && $private !== '';
    }

    /**
     * @param  array<int, PushSubscription>  $subs
     * @param  array<string, mixed>  $extra
     */
    private function dispatch(array $subs, string $title, string $body, ?string $url, array $extra): int
    {
        $webPush = $this->buildWebPush();
        if ($webPush === null) {
            return 0;
        }

        $payload = json_encode(array_filter([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $extra !== [] ? $extra : null,
        ], static fn ($v): bool => $v !== null));
        if ($payload === false) {
            return 0;
        }

        $expiredHashes = [];
        $sent = 0;
        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]);
                $webPush->queueNotification($subscription, $payload, [
                    'TTL' => self::TTL_SECONDS,
                    'urgency' => 'normal',
                ]);
            } catch (Throwable $e) {
                Log::warning('[push] queue failed', [
                    'sub' => $sub->id,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        try {
            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getEndpoint();
                if ($report->isSuccess()) {
                    $sent++;

                    continue;
                }
                $code = $report->getResponse()?->getStatusCode();
                if ($code === 404 || $code === 410) {
                    $expiredHashes[] = PushSubscription::hashEndpoint($endpoint);
                } else {
                    Log::warning('[push] delivery failed', [
                        'endpoint' => $endpoint,
                        'code' => $code,
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::error('[push] flush failed', ['err' => $e->getMessage()]);
        }

        if ($expiredHashes !== []) {
            PushSubscription::query()
                ->whereIn('endpoint_hash', $expiredHashes)
                ->delete();
        }

        return $sent;
    }

    private function buildWebPush(): ?WebPush
    {
        try {
            return new WebPush([
                'VAPID' => [
                    'subject' => (string) config('nexus.webpush.vapid_subject', 'mailto:admin@example.com'),
                    'publicKey' => (string) config('nexus.webpush.vapid_public_key', ''),
                    'privateKey' => (string) config('nexus.webpush.vapid_private_key', ''),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[push] WebPush construction failed', ['err' => $e->getMessage()]);

            return null;
        }
    }
}
