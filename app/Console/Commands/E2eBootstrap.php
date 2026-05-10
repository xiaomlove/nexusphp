<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\E2eUsersSeeder;
use Database\Seeders\SettingsTableSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Nexus\Install\Install;

/**
 * Bring a freshly-migrated installation into a state where the legacy
 * front controller renders pages (instead of redirecting to the install
 * wizard) and Playwright can log in via `takelogin.php`.
 *
 * What it does (idempotent):
 *
 *   1. Runs the default `DatabaseSeeder` if the lookup tables are
 *      empty (signalled by an empty `language` table). This populates
 *      categories, forums, language, faq, rules, and other lookup
 *      tables required for the legacy front controller and the
 *      `users.lang` foreign key.
 *
 *   2. Runs `SettingsTableSeeder` if no `main.*` rows exist.
 *      The default `DatabaseSeeder` does NOT include settings; without
 *      this, `Setting::get('main.defstylesheet')` returns null and
 *      every user creation throws an SQL integrity error.
 *
 *   3. Sets E2E-friendly toggles in the `settings` table:
 *        security.iv                                    = no
 *        security.use_challenge_response_authentication = no
 *      Both default to `yes`. With `iv=yes` every login attempt
 *      requires a CAPTCHA token from a session-tied image. With
 *      challenge-response `=yes` the password is hashed client-side
 *      with JS, so a plain `POST` to `takelogin.php` returns
 *      "Require response parameter".
 *
 *   4. Runs `E2eUsersSeeder` to create three deterministic users
 *      (e2eadmin / e2estaff / e2euser).
 *
 *   5. Creates `dont_delete_install.lock` at the repository root.
 *      When `RUNNING_IN_DOCKER=1` is set in the PHP container, the
 *      legacy `include/core.php` redirects every request to
 *      `install/install.php` until this file exists.
 *
 *   6. Flushes the cached `nexus_settings_in_*` keys in Redis so
 *      step 2 takes effect immediately. PHP-FPM static caches in
 *      already-running workers are not flushed by this command;
 *      after a settings change you may also need
 *      `docker compose restart php` to drop them.
 *
 * Usage:
 *
 *   docker compose exec -T php php artisan e2e:bootstrap
 *
 * This command is intentionally side-effect-heavy and should never
 * run on a production database. It refuses to run unless the
 * environment is `local`, `testing`, or the operator passes
 * `--force`.
 */
class E2eBootstrap extends Command
{
    protected $signature = 'e2e:bootstrap
        {--force : Allow running outside local/testing environments}';

    protected $description = 'Prepare the database and filesystem for end-to-end (Playwright) tests.';

    /**
     * @var array<string, string>
     */
    private const E2E_SETTINGS = [
        'security.iv' => 'no',
        'security.use_challenge_response_authentication' => 'no',
    ];

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        $this->info('==> e2e:bootstrap');

        $this->seedLookupTablesIfMissing();
        $this->seedSettingsIfMissing();
        $this->applyE2eSettings();
        $this->seedE2eUsers();
        $this->createInstallLock();
        $this->flushSettingsCache();
        $this->resetLoginAttempts();

        $this->info('==> done. ready for Playwright.');

        return self::SUCCESS;
    }

    private function guardEnvironment(): bool
    {
        $env = (string) app()->environment();

        if (in_array($env, ['local', 'testing', 'development'], true)) {
            return true;
        }

        if ($this->option('force')) {
            $this->warn(sprintf('environment=%s, --force passed; continuing.', $env));

            return true;
        }

        $this->error(sprintf(
            'refusing to run on environment=%s. pass --force to override.',
            $env,
        ));

        return false;
    }

    private function seedLookupTablesIfMissing(): void
    {
        $count = (int) DB::table('language')->count();

        if ($count > 0) {
            $this->line(sprintf('  lookup tables: language has %d rows, skip DatabaseSeeder', $count));

            return;
        }

        $this->line('  lookup tables: empty, running DatabaseSeeder');
        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        // Surface seeder summary lines so failures are visible to the operator.
        foreach (preg_split("/\r?\n/", trim((string) Artisan::output())) as $line) {
            if ($line !== '') {
                $this->line('    '.$line);
            }
        }
    }

    private function seedSettingsIfMissing(): void
    {
        $count = (int) DB::table('settings')->count();

        if ($count > 0) {
            $this->line(sprintf('  settings: %d rows present, skip SettingsTableSeeder', $count));

            return;
        }

        $this->line('  settings: empty, running SettingsTableSeeder');
        Artisan::call('db:seed', [
            '--class' => SettingsTableSeeder::class,
            '--force' => true,
        ]);
    }

    private function applyE2eSettings(): void
    {
        foreach (self::E2E_SETTINGS as $name => $value) {
            $current = (string) DB::table('settings')->where('name', $name)->value('value');

            if ($current === $value) {
                $this->line(sprintf('  setting %s already = %s', $name, $value));

                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['name' => $name],
                [
                    'value' => $value,
                    'autoload' => 'yes',
                    'updated_at' => now(),
                ],
            );

            $this->line(sprintf('  setting %s: %s -> %s', $name, $current ?: '(missing)', $value));
        }
    }

    private function seedE2eUsers(): void
    {
        $this->line('  running E2eUsersSeeder');
        Artisan::call('db:seed', [
            '--class' => E2eUsersSeeder::class,
            '--force' => true,
        ]);

        // Surface seeder log lines (created/skipped) to the bootstrap log.
        foreach (preg_split("/\r?\n/", trim((string) Artisan::output())) as $line) {
            if ($line !== '') {
                $this->line('    '.$line);
            }
        }
    }

    private function createInstallLock(): void
    {
        $path = base_path(Install::INSTALL_LOCK_FILE);

        if (file_exists($path)) {
            $this->line(sprintf('  install lock %s already exists', Install::INSTALL_LOCK_FILE));

            return;
        }

        file_put_contents($path, 'Created by artisan e2e:bootstrap at '.now()->toIso8601String().PHP_EOL);
        $this->line(sprintf('  install lock %s created', Install::INSTALL_LOCK_FILE));
    }

    private function flushSettingsCache(): void
    {
        try {
            Redis::del('nexus_settings_in_nexus', 'nexus_settings_in_laravel');
            $this->line('  flushed redis keys: nexus_settings_in_nexus, nexus_settings_in_laravel');
        } catch (\Throwable $e) {
            $this->warn(sprintf('  redis flush failed: %s (continuing)', $e->getMessage()));
        }

        $this->flushE2eUserRowCaches();
    }

    /**
     * `get_user_row($id)` caches `user_{id}_content` in Redis for an hour.
     * After we seed fresh `uploaded` / `downloaded` values for the e2e
     * users, the topbar / userdetails ratio would still render the cached
     * `0 / 0 -> ---` until that key expires.
     *
     * Drop the keys belonging to the deterministic e2e users so the next
     * page load reads the freshly-written stats.
     */
    private function flushE2eUserRowCaches(): void
    {
        $usernames = array_map(
            static fn (array $user): string => $user['username'],
            E2eUsersSeeder::USERS,
        );

        $ids = DB::table('users')
            ->whereIn('username', $usernames)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        $keys = array_map(static fn (int $id): string => 'user_'.$id.'_content', $ids);

        try {
            Redis::del(...$keys);
            $this->line('  flushed redis user-row caches: '.implode(', ', $keys));
        } catch (\Throwable $e) {
            $this->warn(sprintf('  user-row cache flush failed: %s (continuing)', $e->getMessage()));
        }
    }

    /**
     * Empty the `loginattempts` table so a re-bootstrap after many
     * failed test runs does not leave the IP banned. The legacy
     * `takelogin.php` rejects further requests from the same client
     * IP after `maxloginattempts` failures (default 7-10) — that
     * threshold is trivially exceeded by Playwright workers if the
     * suite is re-run on a hot stack.
     */
    private function resetLoginAttempts(): void
    {
        $deleted = DB::table('loginattempts')->delete();

        if ($deleted > 0) {
            $this->line(sprintf('  cleared %d row(s) from loginattempts', $deleted));
        }
    }
}
