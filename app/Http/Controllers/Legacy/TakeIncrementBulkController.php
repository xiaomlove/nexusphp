<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/take-increment-bulk.php` (deleted in the same PR).
 *
 * Phase 2 migration. The legacy script was an 88-LOC sysop-only POST
 * handler that bulk-increments a column (seedbonus, attendance_card,
 * invites, uploaded, tmp_invites) for all enabled+confirmed users
 * matching selected class filters, and sends each user a PM.
 *
 * Original legacy flow:
 *   1. `require "../include/bittorrent.php"; dbconn();` bootstrap.
 *   2. Reject non-POST with `stderr("Error", "Permission denied!")`.
 *   3. `require_once(get_langfile_path('increment-bulk.php'));` — loads type map.
 *   4. `loggedinorreturn();` + `get_user_class() < UC_SYSOP` → stderr.
 *   5. Read POST: `sender`, `msg`, `amount`, `type`, `subject`,
 *      `duration`, `classes[]`.
 *   6. Validate: msg/amount/type required, amount numeric, type valid.
 *   7. If type=uploaded, convert amount from GB to bytes via `getsize_int`.
 *   8. If type=tmp_invites, validate duration > 0.
 *   9. Build WHERE from `classes[]` IN-list, apply plugin filter
 *      `role_query_conditions`.
 *  10. Loop over users in chunks of 2000: UPDATE column, send PMs,
 *      and for tmp_invites dispatch `invite:tmp` Artisan command.
 *  11. Redirect to `increment-bulk.php?sent=1&type=$type`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Non-POST → 405 (route is `Route::post(...)`).
 *   - Authenticated user below `User::CLASS_STAFF_LEADER` → `abort(403)`.
 *   - Validation failures → 200 with "Error" text (matches legacy
 *     `stderr()` behaviour — the form does not expect JSON/422).
 *   - Happy path → 302 to `/increment-bulk.php?sent=1&type={type}`.
 *   - `apply_filter('role_query_conditions')` plugin hook preserved.
 *   - `set_time_limit(300)` preserved for large user bases.
 */
class TakeIncrementBulkController extends Controller
{
    /** Chunk size for paging through users. */
    private const CHUNK_SIZE = 2000;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        // Load the legacy language file to get the valid type map.
        $this->ensureLanguageLoaded();
        $lang = $GLOBALS['lang_incrementbulk'] ?? [];
        $validTypeMap = $lang['types'] ?? [];

        $senderId = ($request->input('sender') === 'system') ? 0 : (int) $user->id;
        $dt = date('Y-m-d H:i:s');
        $msg = trim((string) $request->input('msg', ''));
        $amount = $request->input('amount', '');
        $type = (string) $request->input('type', '');
        $subject = trim((string) $request->input('subject', ''));

        // Validation — matches legacy `stderr()` error responses.
        if ($msg === '' || $amount === '' || $type === '') {
            return $this->errorResponse("Don't leave any fields blank.");
        }
        if (! is_numeric($amount)) {
            return $this->errorResponse('amount must be numeric');
        }
        if (! isset($validTypeMap[$type])) {
            return $this->errorResponse('Invalid type');
        }

        // Convert GB to bytes for uploaded type.
        if ($type === 'uploaded') {
            $amount = (int) Format::bytesFromUnit($amount, 'G');
        }

        $isTypeTmpInvite = $type === 'tmp_invites';
        $duration = 0;

        // Build WHERE conditions from class checkboxes.
        $conditions = [];
        $classes = $request->input('classes', []);
        if (! empty($classes) && is_array($classes)) {
            $classIds = array_map('intval', $classes);
            $conditions[] = 'class IN ('.implode(', ', $classIds).')';
        }

        // Plugin filter hook — preserve legacy `apply_filter`.
        $conditions = apply_filter('role_query_conditions', $conditions, $request->except(['_token', '_method']));
        if (empty($conditions)) {
            return $this->errorResponse('No valid filter');
        }

        if ($isTypeTmpInvite) {
            $duration = (int) $request->input('duration', 0);
            if ($duration <= 0) {
                return $this->errorResponse("Invalid duration: {$duration}");
            }
        }

        // Run the bulk operation with extended time limit.
        set_time_limit(300);

        $whereStr = implode(' OR ', $conditions);
        $page = 1;

        while (true) {
            $offset = ($page - 1) * self::CHUNK_SIZE;
            $userRows = NexusDB::select(
                "SELECT id FROM users WHERE ({$whereStr}) AND `enabled` = 'yes' AND `status` = 'confirmed' LIMIT {$offset}, ".self::CHUNK_SIZE
            );

            $idArr = [];
            $msgValues = [];
            foreach ($userRows as $dat) {
                $dat = (array) $dat;
                $idArr[] = (int) $dat['id'];
                $msgValues[] = [
                    'sender' => $senderId,
                    'receiver' => (int) $dat['id'],
                    'added' => $dt,
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }

            if (empty($idArr)) {
                break;
            }

            $idStr = implode(',', $idArr);
            $idRedisKey = sprintf('temporary_invite:%s', microtime(true));
            NexusDB::cache_put($idRedisKey, $idStr);

            if ($isTypeTmpInvite) {
                $command = sprintf('invite:tmp %s %s %s', $idRedisKey, $duration, $amount);
                $output = executeCommand($command, 'string', true);
                do_log(sprintf('command: %s, output: %s', $command, $output));
            } else {
                NexusDB::statement("UPDATE users SET {$type} = {$type} + {$amount} WHERE id IN ({$idStr})");
            }

            foreach ($msgValues as $msgRow) {
                NexusDB::insert('messages', $msgRow);
            }

            $page++;
        }

        return redirect("/increment-bulk.php?sent=1&type={$type}");
    }

    /**
     * Return a chrome-less error response matching the legacy
     * `stderr("Error", $message)` pattern at HTTP 200.
     */
    private function errorResponse(string $message): Response
    {
        return new Response(
            '<html><body><h2>Error</h2><p>'.htmlspecialchars($message).'</p></body></html>',
            200,
        );
    }

    /**
     * Load the per-locale `$lang_incrementbulk` global.
     */
    private function ensureLanguageLoaded(): void
    {
        if (isset($GLOBALS['lang_incrementbulk'])) {
            return;
        }
        $relative = get_langfile_path('increment-bulk.php');
        $path = base_path($relative);
        if (is_file($path)) {
            require_once $path;
        }
    }
}
