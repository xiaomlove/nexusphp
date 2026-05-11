<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Replaces the deleted `public/cron.php` (Phase 2.1.b).
 *
 * The legacy script was a 13 LOC HTTP wrapper that called the
 * legacy `autoclean()` global if `$useCronTriggerCleanUp` was true,
 * and otherwise printed `"Forbidden. Clean-up is set to be
 * browser-triggered."`. The intent was that an external cron job
 * (or curl) would hit `/cron.php` periodically to drive cleanup.
 *
 * Exposing `autoclean()` over HTTP without auth is a footgun (any
 * unauthenticated visitor could trigger it), and the dedicated
 * `nexusphp-scheduler` container already runs Laravel's schedule
 * loop. Phase 2.1.b moves the trigger to a native Artisan command
 * scheduled by `App\Console\Kernel`, deletes `public/cron.php`, and
 * keeps the legacy `autoclean()` function as the body — so the
 * exact same DB-side semantics still apply.
 *
 * `autoclean()` is self-throttling (it consults
 * `avps.lastcleantime` and the `$autoclean_interval_one` config),
 * so calling it `everyMinute()` is safe: most invocations no-op,
 * the rare actual cleanup runs once per interval.
 */
class Autoclean extends Command
{
    /** @var string */
    protected $signature = 'cron:autoclean';

    /** @var string */
    protected $description = 'Run the legacy autoclean() routine. Replaces the deleted public/cron.php.';

    public function handle(): int
    {
        // `autoclean()` lives in `include/functions.php`. The
        // bootstrap step in `bootstrap/app.php` requires that file
        // globally, so the symbol is available in any Laravel
        // process (web, queue, scheduler, console).
        if (! function_exists('autoclean')) {
            $this->error(
                'autoclean() is not loaded. include/functions.php was '
                .'expected to be required by bootstrap/app.php.',
            );

            return Command::FAILURE;
        }

        // The legacy `autoclean()` reads two pieces of state that
        // are normally set up by `include/core.php` in the HTTP
        // entry path — but `core.php` is NOT required by
        // `bootstrap/app.php` (it loads the framework, not the
        // legacy chrome). When run from the scheduler / artisan
        // those bindings are missing:
        //
        //  - `TIMENOW` constant (defined at `include/core.php:35`)
        //  - `$autoclean_interval_one` global (set at
        //    `include/config.php:101` to `$MAIN['...']`).
        //
        // We mirror just those two definitions here. Re-`require`ing
        // the whole of `core.php` would drag in `class_cache_redis`,
        // `Hook::start()`, language loading, and a `checkGuestVisit`
        // call — too many side-effects for a console command.
        defined('TIMENOW') || define('TIMENOW', time());
        if (! isset($GLOBALS['autoclean_interval_one'])) {
            $GLOBALS['autoclean_interval_one'] = (int) get_setting('main.autoclean_interval_one');
        }

        $output = autoclean();

        // `autoclean()` returns either the result of `docleanup()`
        // (truthy when work was done) or false (interval not
        // elapsed yet, or another worker won the race on
        // `lastcleantime`). Mirror the legacy `cron.php` echo so
        // operators eyeballing `php artisan schedule:work` logs see
        // the same "Clean-up not triggered." line they used to see
        // in `curl /cron.php`.
        if ($output) {
            $this->line(rtrim((string) $output, "\n"));
        } else {
            $this->line('Clean-up not triggered.');
        }

        return Command::SUCCESS;
    }
}
