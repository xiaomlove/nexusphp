<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Pins the contract of the migrated `cleanup:full` Artisan command.
 *
 * The deleted `public/docleanup.php` was a 30 LOC sysop-only HTTP
 * page that rendered an HTML envelope around a `docleanup($forceall)`
 * call. The Artisan command preserves the same DB-side semantics
 * (the legacy `docleanup()` is still the body) and adds:
 *   - `--force-all` flag mirroring the legacy `?forceall=1` query.
 *   - "Time consumed: X.XXX seconds." trailer mirroring the legacy
 *     `lang_docleanup['time_consumed']` line.
 *
 * Lives under `tests/Feature/Console/` to mirror `app/Console/Commands/`,
 * same pattern as `AutocleanCommandTest`.
 */
class CleanupFullCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        // The command is auto-loaded via `Kernel::commands()` which
        // calls `$this->load(__DIR__.'/Commands')` — pin that.
        // We don't use `$this->artisan('list', ['--format' => 'raw'])`
        // because Symfony Console 7 dropped the `raw` format. Asking
        // the registry directly is also faster — it avoids running
        // `ListCommand` end-to-end.
        $this->assertArrayHasKey(
            'cleanup:full',
            Artisan::all(),
            'cleanup:full must be auto-loaded via App\\Console\\Kernel::commands().',
        );
    }

    public function test_command_declares_force_all_option(): void
    {
        // Pin the legacy `?forceall=1` → `--force-all` mapping at the
        // option-definition level so a future refactor of the
        // signature string can't silently drop the flag and break
        // ops scripts that have switched from `curl /docleanup.php?forceall=1`
        // to `php artisan cleanup:full --force-all`.
        $command = Artisan::all()['cleanup:full'];
        $this->assertTrue(
            $command->getDefinition()->hasOption('force-all'),
            '`cleanup:full` must declare a `--force-all` option mirroring the legacy ?forceall=1 query.',
        );
    }
}
