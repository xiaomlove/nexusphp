<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Replaces the deleted `public/docleanup.php` (Phase 2 — see
 * `docs/legacy-strategy.md` § "Phase 2" and the Phase 2.1.b
 * `cron.php` → `cron:autoclean` precedent).
 *
 * The legacy script was a 30 LOC sysop-only HTTP page that printed
 * a tiny HTML envelope around a call to `docleanup()` (in
 * `include/cleanup.php`). It was the operator's "force a full
 * cleanup right now, bypassing `autoclean()`'s `lastcleantime`
 * throttle" button — `?forceall=1` even bypassed `docleanup()`'s
 * own per-class TTL guards.
 *
 * Exposing the full cleanup sweep over HTTP without CSRF and with
 * only an in-process `get_user_class() >= UC_SYSOP` check (no rate
 * limit, no audit trail) is a footgun: the legacy function does
 * `set_time_limit(0)` and `ignore_user_abort(1)` and walks every
 * torrent / peer / user / message row, so a misfire can grind for
 * many minutes. Phase 2 moves the trigger to a native Artisan
 * command — only reachable by an operator with shell access on
 * the host, no `public/*.php` surface at all.
 *
 * The actual work still happens inside the legacy `docleanup()`
 * function unchanged, so DB-side semantics are identical to what
 * the legacy page produced.
 *
 * Note: `cron:autoclean` (Phase 2.1.b) is already invoked by the
 * scheduler `everyMinute()`, and `autoclean()` itself drives
 * `docleanup(0)` once per `$autoclean_interval_one` window. This
 * command exists for the out-of-band case the legacy page covered
 * — an operator wanting to run cleanup *now* without waiting for
 * the throttle.
 */
class CleanupFull extends Command
{
    /** @var string */
    protected $signature = 'cleanup:full {--force-all : Bypass docleanup()\'s per-class TTL guards (legacy ?forceall=1)}';

    /** @var string */
    protected $description = 'Force-run the full legacy docleanup() pipeline. Replaces the deleted public/docleanup.php.';

    public function handle(): int
    {
        // `docleanup()` lives in `include/cleanup.php`, which (unlike
        // `include/functions.php` containing `autoclean()`) is NOT
        // required by `bootstrap/app.php`. Load it lazily — the
        // legacy `public/docleanup.php` and `include/cleanup_cli.php`
        // did the same thing. The `IN_TRACKER` guard at the top of
        // `cleanup.php` is satisfied because `include/constants.php`
        // (required by `bootstrap/app.php`) defines `IN_TRACKER` as
        // `false`, and the guard only fails when the constant is
        // *undefined*.
        if (! function_exists('docleanup')) {
            require_once base_path('include/cleanup.php');
        }
        if (! function_exists('docleanup')) {
            $this->error('docleanup() is not loaded after requiring include/cleanup.php.');

            return Command::FAILURE;
        }

        // Mirror `Autoclean`'s CLI environment bootstrap — `docleanup()`
        // reads the `TIMENOW` constant which is normally defined by
        // `include/core.php` in the HTTP entry path. The scheduler /
        // console kernel doesn't go through `core.php`, so populate
        // the constant ourselves. The interval / expiry globals that
        // `docleanup()` `global`s in are sourced from `$MAIN[...]`
        // already set up at framework boot via `Nexus::boot()` →
        // settings load.
        defined('TIMENOW') || define('TIMENOW', time());

        $forceAll = $this->option('force-all') ? 1 : 0;

        $start = microtime(true);
        $output = docleanup($forceAll, true);
        $elapsed = microtime(true) - $start;

        // `docleanup()` returns its terminal status string ("Full
        // cleanup is done"). Mirror what the legacy page wrapped
        // inside its `<p>` tag, and add the `lang_docleanup['time_consumed']`
        // trailer the legacy template also emitted.
        if (is_string($output) && $output !== '') {
            $this->line(rtrim($output, "\n"));
        }
        $this->line(sprintf('Time consumed: %.3f seconds.', $elapsed));

        return Command::SUCCESS;
    }
}
