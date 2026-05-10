import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for NexusPHP end-to-end tests.
 *
 * Run a docker stack with `scripts/e2e-stack-up.sh` first; this
 * config does NOT manage the application server. The `globalSetup`
 * hook only verifies that the stack is reachable and that the three
 * deterministic e2e users exist (created by `php artisan e2e:bootstrap`).
 *
 * Override the base URL with `E2E_BASE_URL=http://host:port`.
 *
 * Recommended local invocations:
 *
 *     npm run e2e               # headless, all browsers from `projects` below
 *     npm run e2e -- --project=chromium
 *     npm run e2e:ui            # opens the Playwright inspector
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost';

export default defineConfig({
    testDir: '.',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: [
        ['list'],
        ['html', { outputFolder: '../../playwright-report', open: 'never' }],
    ],
    outputDir: '../../test-results',
    globalSetup: './global-setup.ts',

    timeout: 30_000,
    expect: { timeout: 10_000 },

    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true,
    },

    projects: [
        {
            // Default project — every spec EXCEPT the `tests/e2e/critical/`
            // suite. Runs `fullyParallel: true` and is where 95% of the
            // suite lives (smoke + behavior + admin + announce).
            name: 'chromium',
            testIgnore: ['**/critical/**'],
            use: { ...devices['Desktop Chrome'] },
        },
        {
            // Critical project — runs every `tests/e2e/critical/*.spec.ts`
            // serially (1 worker) AFTER `chromium` finishes.
            //
            // Two reasons to isolate critical specs from parallel
            // execution:
            //
            //   1. `rate-limit.spec.ts` deliberately fills the
            //      `loginattempts` table for the runner's IP. If a
            //      `/login.php` smoke spec races with it on the same IP
            //      the smoke observes a "Login Locked!" banner and
            //      fails. Project dependencies ensure chromium has
            //      already finished its login traffic.
            //
            //   2. `install-locked.spec.ts` and
            //      `passkey-webauthn.spec.ts` exercise heavy legacy
            //      pages (`/install/install.php` and
            //      `/usercp.php?action=security`) that the CI php -S
            //      single-threaded webserver struggles to serve under
            //      Playwright's 2-worker concurrent load — responses
            //      get truncated mid-stream and the assertions about
            //      late-rendered DOM (`id="passkey_create"`,
            //      "Locked!" banner) flake. Serialising them
            //      sidesteps the contention.
            //
            // Specs in here can leave global state dirty (e.g. fill the
            // `loginattempts` table) as long as they clean up in their
            // own `afterAll` hooks.
            name: 'critical',
            testMatch: ['**/critical/**/*.spec.ts'],
            dependencies: ['chromium'],
            fullyParallel: false,
            workers: 1,
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
