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
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
