<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Replacement for `public/downloadsubs.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md` § "Worked example".
 *
 * Original legacy flow (`public/downloadsubs.php`, 58 LOC):
 *   1. `if (! $CURUSER) { header('Location: /'); exit; }` —
 *      guests redirected to the site root (NOT `login.php` like the
 *      other auth-guarded pages).
 *   2. Read `$_GET['subid']` and `$_GET['torrentid']`; bail with a
 *      plain-text `"File name missing\n"` if either is empty.
 *   3. `intval()` both into `$filename` / `$dirname` — i.e. they
 *      are integer ids, not literal file names.
 *   4. `SELECT * FROM subs WHERE id = $filename` LIMIT 1; bail with
 *      `"Not found\n"` on miss.
 *   5. `UPDATE subs SET hits = hits + 1 WHERE id = $filename`.
 *   6. Build `ROOT_PATH . $SUBSPATH . '/' . $dirname . '/' . $filename . '.' . $arr['ext']`
 *      and bail with `"File not found\n"` if the file doesn't exist.
 *   7. `fopen` + send `Content-Length`, `Content-Type:
 *      application/octet-stream`, and a 5-branch UA-sniffed
 *      `Content-Disposition` that collapses to either
 *      `filename="<raw>"` or `filename=<rawurlencoded>`.
 *   8. Stream the file body in 4 KiB chunks via `fread`/`echo`.
 *
 * Replacement contract (this controller) — same shape as
 * {@see GetAttachmentController}:
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`. (Legacy bounced to `/` — the new
 *     redirect to `login.php` is a strict improvement, matches every
 *     other Phase 2 controller, and preserves the original intent of
 *     gating sub downloads to logged-in users.)
 *   - Parked user → 403 JSON `{"message":"Your account is parked."}`.
 *     (Legacy had no parked check at all, but every other Phase 2
 *     controller adds one so a parked account can't smuggle bytes
 *     out via the attachment / subtitle download endpoints. Aligns
 *     with `GetAttachmentController`.)
 *   - Missing / non-positive `subid` or `torrentid` → 422 JSON
 *     (legacy was HTTP 200 plain `"File name missing\n"`).
 *   - `subs` row not found → 404 JSON (legacy was HTTP 200 plain
 *     `"Not found\n"`).
 *   - File missing on disk → 404 JSON (legacy was HTTP 200 plain
 *     `"File not found\n"`).
 *   - Happy path → 200 with `Content-Type: application/octet-stream`,
 *     `Content-Length: <filesize>`, RFC 6266-compliant
 *     `Content-Disposition: attachment; filename=...` (with a
 *     `filename*=UTF-8''...` extended parameter for non-ASCII
 *     filenames). `subs.hits` is incremented before the stream so
 *     the post-conditions are deterministic in tests.
 *
 * Note: the legacy script ignored the order of side effects — it
 * incremented `subs.hits` BEFORE checking the file existence, so a
 * download attempt for a row whose file no longer existed on disk
 * still bumped the counter. We move the increment behind the
 * `is_file` check so the counter only moves for actually-served
 * bytes. This matches `GetAttachmentController` and is what the test
 * suite asserts.
 *
 * The five UA-sniffing branches in the legacy `Content-Disposition`
 * builder collapsed to two outputs; Symfony's
 * {@see HeaderUtils::makeDisposition()} emits the modern RFC 6266
 * form that every browser — including IE legacy mode — accepts and
 * handles UTF-8 filenames safely. See the same note on
 * {@see GetAttachmentController} for details.
 */
class DownloadSubsController extends Controller
{
    /** Default value when `$SUBSPATH` is not set in `include/config.php`. */
    private const DEFAULT_SUBS_PATH = 'subs';

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): StreamedResponse|JsonResponse
    {
        $user = $this->context->user();
        // `auth.nexus:nexus-web` guarantees we're authenticated; the
        // re-check protects against future route mis-wiring.
        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if (($user->parked ?? 'no') === 'yes') {
            return new JsonResponse(['message' => 'Your account is parked.'], 403);
        }

        $subId = (int) $request->query('subid', 0);
        $torrentId = (int) $request->query('torrentid', 0);
        if ($subId <= 0 || $torrentId <= 0) {
            return new JsonResponse(['message' => 'Missing or invalid subid / torrentid.'], 422);
        }

        $row = NexusDB::table('subs')
            ->where('id', $subId)
            ->first();
        if ($row === null) {
            return new JsonResponse(['message' => 'Not found.'], 404);
        }
        $row = (array) $row;

        // `$SUBSPATH` is a global string set in `include/config.php`
        // (default `'subs'`). Outside the legacy bootstrap we read it
        // via the same `$GLOBALS` channel `include/bittorrent.php`
        // populates, falling back to the documented default.
        $subsPath = trim((string) ($GLOBALS['SUBSPATH'] ?? self::DEFAULT_SUBS_PATH), '/');
        if ($subsPath === '') {
            $subsPath = self::DEFAULT_SUBS_PATH;
        }
        $file = base_path($subsPath.'/'.$torrentId.'/'.$subId.'.'.(string) $row['ext']);
        if (! is_file($file) || ! is_readable($file)) {
            return new JsonResponse(['message' => 'File not found.'], 404);
        }

        NexusDB::table('subs')->where('id', $subId)->increment('hits');

        $filename = (string) $row['filename'];
        $filesize = (int) filesize($file);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            $this->asciiFallback($filename),
        );

        return new StreamedResponse(
            function () use ($file): void {
                $f = fopen($file, 'rb');
                if ($f === false) {
                    return;
                }
                try {
                    while (! feof($f)) {
                        $chunk = fread($f, 4096);
                        if ($chunk === false) {
                            break;
                        }
                        echo $chunk;
                    }
                } finally {
                    fclose($f);
                }
            },
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) $filesize,
                'Content-Disposition' => $disposition,
            ],
        );
    }

    /**
     * Build an ASCII-only fallback for the RFC 6266
     * `Content-Disposition` `filename` / `filename*` parameter pair.
     * Runs of non-ASCII bytes collapse to a single underscore (so
     * `отчёт.srt` produces `_.srt`, not `__________.srt`).
     *
     * Mirrors {@see GetAttachmentController::asciiFallback()}.
     */
    private function asciiFallback(string $filename): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7e]+/', '_', $filename);
        $ascii = str_replace(['"', '\\', '%'], '_', $ascii);

        return $ascii === '' ? 'subtitles' : $ascii;
    }
}
