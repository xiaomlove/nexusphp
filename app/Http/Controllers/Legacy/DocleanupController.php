<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/docleanup.php` (deleted in the same PR).
 *
 * Phase 2 batch #12 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow (30 LOC):
 *   1. `ob_start();` + `require_once "../include/bittorrent.php"`
 *      + `dbconn();`.
 *   2. `get_user_class() < UC_SYSOP` → `die('forbidden')`.
 *   3. `require get_langfile_path();` — loads `$lang_docleanup`.
 *   4. Prints a bare `<html><body>` envelope with the
 *      "running cleanup..." copy.
 *   5. `$_GET['forceall']` truthy → `$forceall = 1`, else `0`
 *      (and prints the "force" notice).
 *   6. `require_once "include/cleanup.php"` + `docleanup($forceall, 1)`
 *      — runs the whole cleanup job synchronously and inlines the
 *      function's return value into the response.
 *   7. Prints the elapsed time + "done" line, closes the envelope.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *     The legacy `die('forbidden')` returned HTTP 200 with a plain
 *     "forbidden" body, which is the same anti-pattern the rest of
 *     Phase 2 replaces with a real 403.
 *   - GET (with or without `?forceall=1`) → invokes `docleanup()`
 *     synchronously and returns a chrome-less HTML envelope wrapping
 *     the result + elapsed-time line.
 *
 * The function is a long-running, side-effecting cleanup job that
 * lives in `include/cleanup.php` and touches roughly 20 tables. We
 * deliberately do NOT migrate the body — `php artisan cron:autoclean`
 * is the modern entry point (a Laravel Artisan command wired into the
 * scheduler) and `docleanup($forceall, 1)` is the legacy in-process
 * variant that bens still rely on to trigger a manual run from the
 * browser. The wrapper preserves the legacy contract so the
 * "Run Cleanup" button in the staff panel keeps working until the
 * Artisan command becomes the single source of truth in Phase 5.
 *
 * The chrome-less envelope follows the precedent set by every other
 * Phase 2 controller in this directory — the legacy `stdhead()` /
 * `stdfoot()` chrome is not reproduced. This page is a sysop-only
 * tool and the original page used the chrome only as a viewport.
 */
class DocleanupController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        $forceall = $request->query('forceall') ? 1 : 0;

        // `include/cleanup.php` defines `docleanup()` as a free
        // function in the global namespace. It is already loaded via
        // the legacy bootstrap that LegacyContext requires, but the
        // `require_once` keeps the file's contract self-evident and
        // is a no-op when the symbol is already defined.
        require_once base_path('include/cleanup.php');

        if (! function_exists('docleanup')) {
            // include/cleanup.php is expected to be required either by
            // the legacy bootstrap or by the line above; the `function_exists`
            // guard is a defensive matchup with `App\Console\Commands\Autoclean`,
            // which uses the same pattern for `autoclean()`.
            abort(500, 'docleanup() is not loaded. include/cleanup.php was expected to be required.');
        }

        $tstart = microtime(true);
        $result = (string) call_user_func('docleanup', $forceall, 1);
        $tend = microtime(true);
        $elapsed = $tend - $tstart;

        $forceNotice = $forceall === 0
            ? '<br />Force-all mode disabled — pass <code>?forceall=1</code> to override the in-table cooldowns.'
            : '';
        $elapsedLine = sprintf('Time consumed: %.4f seconds<br />', $elapsed);

        $body = '<p>Running cleanup...'.$forceNotice.'</p>'."\n"
            .'<p>'.$result.'</p>'."\n"
            .$elapsedLine
            .'Done<br />';

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Cleanup</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
