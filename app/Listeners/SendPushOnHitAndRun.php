<?php

namespace App\Listeners;

use App\Events\HitAndRunCreated;
use App\Events\HitAndRunUpdated;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Models\User;
use App\Services\PushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Push H&R / penalty notifications to the affected user. Two paths:
 *
 * 1. {@see handleCreated} — a new HnR record was created (status =
 *    STATUS_INSPECTING). User gets a heads-up that a torrent is being
 *    watched so they can fix the seed time before the inspection
 *    deadline.
 *
 * 2. {@see handleUpdated} — status flipped to STATUS_UNREACHED (penalty
 *    confirmed) or STATUS_PARDONED (penalty forgiven). STATUS_REACHED
 *    is silent — user did the right thing, no need to ping them.
 *
 * All paths are gated on {@see User::acceptNotification('hit_and_run')}
 * and queued so the announce / cron caller never blocks on VAPID.
 */
class SendPushOnHitAndRun implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly PushDispatcher $push) {}

    public function handleCreated(HitAndRunCreated $event): void
    {
        $hnr = $event->model;
        if (! $hnr instanceof HitAndRun) {
            return;
        }
        $this->dispatchInspecting($hnr);
    }

    public function handleUpdated(HitAndRunUpdated $event): void
    {
        $hnr = $event->model;
        if (! $hnr instanceof HitAndRun) {
            return;
        }
        $status = (int) $hnr->status;
        // Only notify on confirmation (UNREACHED) or pardon — REACHED
        // is a quiet success, INSPECTING shouldn't refire on every save.
        if ($status === HitAndRun::STATUS_UNREACHED) {
            $this->dispatchUnreached($hnr);
        } elseif ($status === HitAndRun::STATUS_PARDONED) {
            $this->dispatchPardoned($hnr);
        }
    }

    private function dispatchInspecting(HitAndRun $hnr): void
    {
        $user = $this->lookupUser((int) $hnr->uid);
        if ($user === null) {
            return;
        }
        $name = $this->torrentName((int) $hnr->torrent_id);
        $this->push->sendToUser(
            (int) $user->id,
            'H&R warning',
            "Your seeding of {$name} is being inspected. Make sure to seed long enough to avoid a penalty.",
            '/hitandruns.php',
            ['kind' => 'hit_and_run', 'status' => 'inspecting', 'torrentId' => (int) $hnr->torrent_id],
        );
    }

    private function dispatchUnreached(HitAndRun $hnr): void
    {
        $user = $this->lookupUser((int) $hnr->uid);
        if ($user === null) {
            return;
        }
        $name = $this->torrentName((int) $hnr->torrent_id);
        $reason = (string) Str::limit((string) ($hnr->comment ?? ''), 120);
        $body = "An H&R penalty was confirmed for {$name}.";
        if ($reason !== '') {
            $body .= ' '.$reason;
        }
        $this->push->sendToUser(
            (int) $user->id,
            'H&R penalty confirmed',
            $body,
            '/hitandruns.php',
            ['kind' => 'hit_and_run', 'status' => 'unreached', 'torrentId' => (int) $hnr->torrent_id],
        );
    }

    private function dispatchPardoned(HitAndRun $hnr): void
    {
        $user = $this->lookupUser((int) $hnr->uid);
        if ($user === null) {
            return;
        }
        $name = $this->torrentName((int) $hnr->torrent_id);
        $this->push->sendToUser(
            (int) $user->id,
            'H&R pardoned',
            "Good news — your H&R for {$name} has been pardoned.",
            '/hitandruns.php',
            ['kind' => 'hit_and_run', 'status' => 'pardoned', 'torrentId' => (int) $hnr->torrent_id],
        );
    }

    private function lookupUser(int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }
        $user = User::query()->find($userId, ['id', 'notifs']);
        if ($user === null) {
            return null;
        }
        if (! $user->acceptNotification('hit_and_run')) {
            return null;
        }

        return $user;
    }

    private function torrentName(int $torrentId): string
    {
        if ($torrentId <= 0) {
            return 'a torrent';
        }
        $torrent = Torrent::query()->find($torrentId, ['id', 'name']);
        $name = (string) ($torrent->name ?? '');

        return $name !== '' ? Str::limit($name, 80) : 'a torrent';
    }
}
