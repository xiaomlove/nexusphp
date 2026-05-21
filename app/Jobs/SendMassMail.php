<?php

namespace App\Jobs;

use App\Http\Requests\Legacy\SendMassMailRequest;
use App\Models\Setting;
use App\Repositories\ToolRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

/**
 * Queued counterpart of the legacy `foreach ($rows as $arr)` mail
 * loop in `public/massmail.php` (deleted in the same PR).
 *
 * The legacy script ran the loop inline inside the HTTP request,
 * so the browser blocked for as long as the SMTP fan-out took (no
 * `set_time_limit` was set). On the dispatching side we now redirect
 * immediately — this job runs in the queue worker.
 *
 * Per-page contract:
 *   - Selects every confirmed, enabled `users` row whose `class`
 *     matches the controller-validated `class <op> <threshold>`
 *     comparison, paginates at `$pageSize` per chunk.
 *   - Sends one e-mail per matched user via
 *     `ToolRepository::sendMail($email, $subject, $body)`. SMTP
 *     errors on a single recipient are logged via `do_log` but do
 *     NOT abort the loop — the legacy script also kept iterating
 *     after a `sent_mail` returned `false`. The final
 *     `if ($success)` check in the legacy code only inspected the
 *     LAST recipient's status (a long-standing bug); we don't
 *     reproduce that, since the redirect now fires before the
 *     job finishes anyway.
 *   - Body is the legacy envelope:
 *       `Message received from <SITENAME> on <Y-m-d H:i:s>.`
 *       `------------------------------------------`
 *       `<message>`
 *       `------------------------------------------`
 *       `<SITENAME>`
 *
 * `WithoutOverlapping` is keyed by sender so two staffers can fan
 * out concurrently, but the same staffer can't double-dispatch
 * overlapping batches.
 */
class SendMassMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const DEFAULT_PAGE_SIZE = 2000;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly int $senderId,
        public readonly string $operator,
        public readonly int $threshold,
        public readonly string $subject,
        public readonly string $message,
        public readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('send-mass-mail-'.$this->senderId))->expireAfter(7200)];
    }

    public function handle(): void
    {
        if (! in_array($this->operator, SendMassMailRequest::VALID_OPERATORS, true)) {
            do_log("[SendMassMail]: invalid operator '{$this->operator}', aborting", 'warning');

            return;
        }

        $siteName = (string) Setting::get('basic.SITENAME');
        $tools = app(ToolRepository::class);
        $page = 1;
        $totalSent = 0;

        while (true) {
            $offset = ($page - 1) * $this->pageSize;
            $size = $this->pageSize;
            $rows = NexusDB::select(
                "SELECT id, username, email FROM users WHERE class {$this->operator} {$this->threshold} ".
                "AND `enabled` = 'yes' AND `status` = 'confirmed' LIMIT $offset, $size"
            );
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $row = (array) $row;
                $email = (string) ($row['email'] ?? '');
                if ($email === '') {
                    continue;
                }
                $body = $this->renderBody($siteName);
                try {
                    $tools->sendMail($email, $this->subject, $body, false);
                    $totalSent++;
                } catch (\Throwable $e) {
                    do_log("[SendMassMail]: send to $email failed: ".$e->getMessage(), 'error');
                }
            }

            $page++;
        }

        do_log("[SendMassMail]: sender={$this->senderId} operator={$this->operator} threshold={$this->threshold} sent={$totalSent}");
    }

    private function renderBody(string $siteName): string
    {
        $now = date('Y-m-d H:i:s');

        return "Message received from $siteName on $now.\n".
            "---------------------------------------------------------------------\n\n".
            $this->message."\n\n".
            "---------------------------------------------------------------------\n$siteName\n";
    }
}
