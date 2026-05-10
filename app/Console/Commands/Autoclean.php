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
