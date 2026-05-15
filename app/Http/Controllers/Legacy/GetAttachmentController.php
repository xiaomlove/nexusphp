<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Replacement for `public/getattachment.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md` § "Worked example".
 *
 * Original legacy flow (`public/getattachment.php`, 57 LOC):
 *   1. `loggedinorreturn()` — redirect guests to `/login.php?returnto=...`.
 *   2. `parked()` — block parked users via `stderr()`.
 *   3. `$id = (int) $_GET['id']`; bail on 0 with `exit('Invalid id.')`.
 *   4. `$dlkey = $_GET['dlkey']`; bail on empty with `exit('Invalid key')`.
 *   5. `SELECT * FROM attachments WHERE id = ? AND dlkey = ?` LIMIT 1.
 *   6. `is_file($httpdirectory_attachment . '/' . $row['location'])`;
 *      bail with `exit('File not found or cannot be read.')`.
 *   7. `fopen`, send `Content-Length`, `Content-Type: application/octet-stream`,
 *      `Content-Disposition: attachment; filename=...` (with a 5-branch
 *      UA-sniff that all collapse to the same `filename="..."`/
 *      `filename=urlencoded` pair).
 *   8. Stream the file body in 4 KiB chunks via `fread`/`echo`.
 *   9. `UPDATE attachments SET downloads = downloads + 1 WHERE id = ?`.
 *  10. `$Cache->delete_value('attachment_' . $dlkey . '_content')`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...` (same URL the legacy
 *     `loggedinorreturn()` produced).
 *   - Parked user → 403 JSON (legacy `parked()` called `stderr()`
 *     which rendered HTTP 200 with the legacy chrome; tightened for
 *     the same reason as `AdRedirectController` / `AllAgentsController`).
 *   - Missing / non-positive `id` → 422 JSON.
 *   - Missing / empty `dlkey` → 422 JSON.
 *   - `attachments` row not found for the `(id, dlkey)` pair → 404 JSON.
 *   - File missing on disk → 404 JSON. (Legacy `exit('File not found
 *     or cannot be read.')` rendered as HTTP 200 plain text;
 *     tightened to a real 404 in line with the rest of Phase 2.)
 *   - Happy path → 200 with `Content-Type: application/octet-stream`,
 *     `Content-Length: <filesize>`, RFC 6266-compliant
 *     `Content-Disposition: attachment; filename="..."` (with a
 *     `filename*=UTF-8''...` fallback for non-ASCII filenames).
 *     `attachments.downloads` is incremented and the
 *     `attachment_<dlkey>_content` cache entry is invalidated
 *     after the response headers are committed but before the body
 *     is streamed; the legacy script did both *after* the body but
 *     a stream/echo crash there would have left the row out of sync
 *     anyway. Doing the side effects before the stream gives us
 *     deterministic post-conditions in tests without changing the
 *     observable header/body for clients.
 *
 * Filesystem path resolution: the legacy script ran with cwd
 * `public/`, so `is_file($httpdirectory_attachment . '/' . location)`
 * looked at `public/<httpdirectory>/<location>`. Laravel controllers
 * run with cwd at the project root, so we anchor the same lookup at
 * `public_path()` — yielding the exact same filesystem path as the
 * legacy.
 *
 * The five UA-sniffing branches in the legacy `Content-Disposition`
 * builder all reduce to two outputs: `filename="<raw>"` for
 * Mozilla/WebKit/Opera and `filename=<rawurlencoded>` for IE / other.
 * Symfony's {@see HeaderUtils::makeDisposition()} produces an
 * RFC 6266 header (`filename="ascii-fallback"; filename*=UTF-8''<pct>`)
 * that every modern browser — and the Mozilla/Firefox/Opera codepath
 * the legacy used — handles correctly. IE/Edge legacy mode also
 * accepts `filename*`. The two callers that build `getattachment.php`
 * links today (`include/functions.php:210` and `_db/.../*sql` dumps)
 * pass `filename` straight through from `attachments.filename`, which
 * is bounded to 255 chars and accepts UTF-8 (`utf8mb4`), so this is
 * strictly an improvement.
 */
class GetAttachmentController extends Controller
{
    /** Default value when `attachment.httpdirectory` is not set in `settings`. */
    private const DEFAULT_HTTP_DIRECTORY = 'attachments';

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

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return new JsonResponse(['message' => 'Invalid id.'], 422);
        }

        $dlkey = (string) $request->query('dlkey', '');
        if ($dlkey === '') {
            return new JsonResponse(['message' => 'Invalid key.'], 422);
        }

        $row = NexusDB::table('attachments')
            ->where('id', $id)
            ->where('dlkey', $dlkey)
            ->first();
        if ($row === null) {
            return new JsonResponse(['message' => 'No attachment found.'], 404);
        }
        $row = (array) $row;

        $httpDir = trim(
            (string) (Setting::getByName('attachment.httpdirectory') ?? self::DEFAULT_HTTP_DIRECTORY),
            '/',
        );
        if ($httpDir === '') {
            $httpDir = self::DEFAULT_HTTP_DIRECTORY;
        }
        $filelocation = public_path($httpDir.'/'.(string) $row['location']);
        if (! is_file($filelocation) || ! is_readable($filelocation)) {
            return new JsonResponse(['message' => 'File not found or cannot be read.'], 404);
        }

        // Increment + cache-bust before the stream so the
        // post-conditions are deterministic even when the client
        // tears the connection down mid-body. Mirrors the legacy
        // semantics for a successful download (download counter +
        // invalidate `attachment_<dlkey>_content`).
        NexusDB::table('attachments')->where('id', $id)->increment('downloads');
        NexusDB::cache_del('attachment_'.$dlkey.'_content');

        $filename = (string) $row['filename'];
        $filesize = (int) $row['filesize'];

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            $this->asciiFallback($filename),
        );

        return new StreamedResponse(
            function () use ($filelocation): void {
                $f = fopen($filelocation, 'rb');
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
     * Build an ASCII-only fallback for the RFC 6266 `filename="..."`
     * parameter. `HeaderUtils::makeDisposition()` validates that the
     * fallback contains no non-ASCII chars, so we replace anything
     * outside `printable ASCII` with `_` and strip the few characters
     * that have meaning inside a quoted-string (`"`, `\`, `%`).
     */
    private function asciiFallback(string $filename): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7e]/', '_', $filename);
        $ascii = str_replace(['"', '\\', '%'], '_', $ascii);

        return $ascii === '' ? 'attachment' : $ascii;
    }
}
