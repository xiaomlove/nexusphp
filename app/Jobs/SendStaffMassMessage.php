<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;

/**
 * Queued counterpart of the legacy `while(true) { ... LIMIT ?,10000 }`
 * loop in `public/takestaffmess.php`.
 *
 * The legacy script ran the loop inline inside the HTTP request,
 * with `set_time_limit(300)` capping it at 5 minutes; the browser
 * blocked for the entire duration, and the redirect to
 * `staffmess.php?sent=1` only fired after the last batch. On the
 * dispatching side we now redirect immediately to
 * `staffmess.php?queued=1` — this job runs in the queue worker
 * (which the `scheduler` / dedicated queue container already runs).
 *
 * Contract:
 *   - Selects every confirmed, enabled `users` row whose `class`
 *     matches the controller-computed WHERE clause (`$conditions`),
 *     pages through them at `$pageSize` per chunk, and inserts
 *     one `messages` row per user (sender, receiver, added, subject,
 *     msg). Other `messages` columns (unread, location, saved)
 *     fall back to schema defaults (yes, 1, no).
 *   - `senderId = 0` represents the legacy "System" radio option;
 *     `senderId = $user->id` for "Self".
 *   - `$conditions` is the array of WHERE alternatives already
 *     joined with ` OR `. The controller builds the `class IN (...)`
 *     clause and runs `apply_filter('role_query_conditions', ...)`
 *     so plugins can add their own role filters — same as legacy.
 *   - `WithoutOverlapping` is keyed by `sender_id` so two staffers
 *     can fan out concurrently, but the same staffer can't double-
 *     dispatch overlapping mass-PM jobs.
 *
 * The legacy script did NOT call `clear_inbox_count_cache($uid)` for
 * receivers, so user-side `_unread_message_count` Redis keys stay
 * stale for up to 60s (their TTL) after this job finishes. We
 * preserve that exact behaviour — fixing it is a separate change
 * because the per-receiver cache-bust is a measurable Redis write
 * fan-out on a 10k-batch.
 */
class SendStaffMassMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Match the legacy `LIMIT ?,10000` literal so a single job pages
     * through the user table the same way the inline PHP-FPM loop
     * did. Configurable so Feature tests can run against a tiny
     * `$pageSize` without seeding 10k users.
     */
    public const DEFAULT_PAGE_SIZE = 10000;

    /**
     * One try only — this job is not idempotent (running it twice
     * would double-insert messages). Failures should be inspected
     * manually before re-running. Mirrors the legacy "one POST, one
     * fan-out" contract.
     */
    public int $tries = 1;

    /**
     * Cap at one hour. The legacy `set_time_limit(300)` was 5
     * minutes per request, but those requests blocked the browser
     * — moving to a queue worker lets us pick a more realistic
     * upper bound for very large user tables.
     */
    public int $timeout = 3600;

    /**
     * @param  array<int, string>  $conditions  WHERE alternatives,
     *                                          already joined with ` OR `
     *                                          by the controller.
     */
    public function __construct(
        public readonly int $senderId,
        public readonly string $subject,
        public readonly string $msg,
        public readonly array $conditions,
        public readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('send-staff-mass-message-'.$this->senderId))->expireAfter(7200)];
    }

    public function handle(): void
    {
        if (empty($this->conditions)) {
            do_log('[SendStaffMassMessage]: no conditions, aborting', 'warning');

            return;
        }
        $whereStr = implode(' OR ', $this->conditions);
        $now = Carbon::now()->toDateTimeString();
        $page = 1;
        $totalInserted = 0;
        while (true) {
            $offset = ($page - 1) * $this->pageSize;
            $size = $this->pageSize;
            $rows = [];
            $userRows = NexusDB::select(
                "SELECT id FROM users WHERE ($whereStr) AND `enabled` = 'yes' AND `status` = 'confirmed' LIMIT $offset, $size"
            );
            foreach ($userRows as $dat) {
                $rows[] = [
                    'sender' => $this->senderId,
                    'receiver' => (int) $dat['id'],
                    'added' => $now,
                    'subject' => $this->subject,
                    'msg' => $this->msg,
                ];
            }
            if (empty($rows)) {
                break;
            }
            NexusDB::table('messages')->insert($rows);
            $totalInserted += count($rows);
            $page++;
        }
        do_log("[SendStaffMassMessage]: sender={$this->senderId} inserted={$totalInserted}");
    }
}
