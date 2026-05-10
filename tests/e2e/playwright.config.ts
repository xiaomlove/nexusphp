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
            // Default project — every spec except `destructive`-tagged
            // ones (currently only the rate-limit spec, which bans the
            // requesting IP and would knock out parallel /login.php
            // smokes). Tests run with `fullyParallel: true` here.
            name: 'chromium',
            testIgnore: ['**/critical/rate-limit.spec.ts'],
            use: { ...devices['Desktop Chrome'] },
        },
        {
            // Destructive project — runs after `chromium` finishes with
            // a single worker. Specs in here can leave global state
            // dirty (e.g. fill the loginattempts table) as long as they
            // clean up in their own `afterAll` hooks.
            name: 'destructive',
            testMatch: ['**/critical/rate-limit.spec.ts'],
            dependencies: ['chromium'],
            fullyParallel: false,
            workers: 1,
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
