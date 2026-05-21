<?php

namespace App\Jobs;

use App\Http\Requests\Legacy\SendIncrementBulkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;

/**
 * Queued counterpart of the legacy `while(true) { ... LIMIT ?,2000 }`
 * loop in `public/take-increment-bulk.php` (deleted in the same PR).
 *
 * The legacy script ran the loop inline inside the HTTP request,
 * with `set_time_limit(300)` capping it at 5 minutes; the browser
 * blocked for the entire duration, and the redirect to
 * `increment-bulk.php?sent=1&type=$type` only fired after the last
 * batch. On the dispatching side (`TakeIncrementBulkController`) we
 * now redirect immediately — this job runs in the queue worker.
 *
 * Per-page contract:
 *   - Selects every confirmed, enabled `users` row whose `class`
 *     matches the controller-computed WHERE clause (`$conditions`),
 *     pages through them at `$pageSize` per chunk.
 *   - For each user in the page, queues a `messages` row (sender,
 *     receiver, added, subject, msg). Other columns fall back to
 *     schema defaults.
 *   - For `type !== 'tmp_invites'`:
 *     `UPDATE users SET <type> = <type> + <amount> WHERE id IN (...)`.
 *     `<type>` is whitelisted by the FormRequest against
 *     `SendIncrementBulkRequest::VALID_TYPES`, all of which are
 *     real `users` columns (`seedbonus`, `attendance_card`,
 *     `invites`, `uploaded`).
 *   - For `type === 'tmp_invites'`: stores the user-id list in
 *     Redis under `temporary_invite:<microtime>` and dispatches
 *     `GenerateTemporaryInvite($redisKey, $duration, $amount)`
 *     directly — same contract as the legacy `executeCommand
 *     'invite:tmp ...'` shell-out, but without the per-page
 *     `php artisan` subprocess (the Artisan command itself just
 *     dispatches the same job).
 *
 * `senderId = 0` represents the legacy "System" radio option;
 * `senderId = $user->id` for "Self".
 *
 * `WithoutOverlapping` is keyed by sender so two staffers can fan
 * out concurrently, but the same staffer can't double-dispatch
 * overlapping batches.
 */
class SendIncrementBulkBonus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Match the legacy `LIMIT ?,2000` literal so a single job pages
     * through the user table the same way the inline PHP-FPM loop
     * did. Configurable so Feature tests can run against a tiny
     * `$pageSize` without seeding 2k users.
     */
    public const DEFAULT_PAGE_SIZE = 2000;

    public int $tries = 1;

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
        public readonly string $type,
        public readonly int $amount,
        public readonly int $duration,
        public readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('send-increment-bulk-'.$this->senderId))->expireAfter(7200)];
    }

    public function handle(): void
    {
        if (empty($this->conditions)) {
            do_log('[SendIncrementBulkBonus]: no conditions, aborting', 'warning');

            return;
        }

        if (! in_array($this->type, SendIncrementBulkRequest::VALID_TYPES, true)) {
            do_log("[SendIncrementBulkBonus]: invalid type {$this->type}, aborting", 'warning');

            return;
        }

        $whereStr = implode(' OR ', $this->conditions);
        $now = Carbon::now()->toDateTimeString();
        $page = 1;
        $totalUsers = 0;
        $isTmpInvite = $this->type === SendIncrementBulkRequest::TYPE_TMP_INVITES;

        while (true) {
            $offset = ($page - 1) * $this->pageSize;
            $size = $this->pageSize;
            $userRows = NexusDB::select(
                "SELECT id FROM users WHERE ($whereStr) AND `enabled` = 'yes' AND `status` = 'confirmed' LIMIT $offset, $size"
            );
            $idArr = [];
            $messageRows = [];
            foreach ($userRows as $dat) {
                $dat = (array) $dat;
                $uid = (int) $dat['id'];
                $idArr[] = $uid;
                $messageRows[] = [
                    'sender' => $this->senderId,
                    'receiver' => $uid,
                    'added' => $now,
                    'subject' => $this->subject,
                    'msg' => $this->msg,
                ];
            }
            if (empty($idArr)) {
                break;
            }

            $idStr = implode(',', $idArr);

            if ($isTmpInvite) {
                $idRedisKey = sprintf('temporary_invite:%s', microtime(true));
                NexusDB::cache_put($idRedisKey, $idStr);
                GenerateTemporaryInvite::dispatch($idRedisKey, (string) $this->duration, (string) $this->amount);
            } else {
                NexusDB::statement(
                    "UPDATE users SET `{$this->type}` = `{$this->type}` + {$this->amount} WHERE id IN ($idStr)"
                );
            }

            NexusDB::table('messages')->insert($messageRows);
            $totalUsers += count($idArr);
            $page++;
        }

        do_log("[SendIncrementBulkBonus]: sender={$this->senderId} type={$this->type} amount={$this->amount} users={$totalUsers}");
    }
}
