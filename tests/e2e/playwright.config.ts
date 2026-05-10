import { defineConfig, devices, Project } from '@playwright/test';

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
 * Override the browser matrix with
 * `E2E_BROWSERS=chromium,firefox,webkit` (comma-separated). The
 * default is `chromium`, which is what every PR runs against.
 * The nightly cross-browser job sets
 * `E2E_BROWSERS=chromium,firefox,webkit`.
 *
 * Recommended local invocations:
 *
 *     npm run e2e               # headless, default chromium project
 *     npm run e2e -- --project=chromium
 *     E2E_BROWSERS=firefox,webkit npm run e2e
 *     npm run e2e:ui            # opens the Playwright inspector
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost';

const requestedBrowsers = (process.env.E2E_BROWSERS ?? 'chromium')
    .split(',')
    .map((b) => b.trim())
    .filter(Boolean);

const projects: Project[] = [];

if (requestedBrowsers.includes('chromium')) {
    projects.push({
        // Default project — every spec EXCEPT the `tests/e2e/critical/`
        // suite. Runs `fullyParallel: true` and is where 95% of the
        // suite lives (smoke + behavior + admin + announce).
        name: 'chromium',
        testIgnore: ['**/critical/**'],
        use: { ...devices['Desktop Chrome'] },
    });
    projects.push({
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
    });
}

if (requestedBrowsers.includes('firefox')) {
    // Firefox / WebKit cross-browser projects deliberately exclude the
    // critical suite. The critical specs are:
    //
    //   - `install-locked` — protocol-level via APIRequestContext, so
    //     browser engine doesn't matter.
    //   - `passkey-webauthn` — browser-engine-specific WebAuthn
    //     virtual authenticator support is uneven (Firefox has it
    //     behind a flag, WebKit on Linux is unsupported), and the
    //     ajax.php JSON contract is engine-independent anyway.
    //   - `rate-limit` — fills the `loginattempts` table, leaving it
    //     to two more browsers would cause cross-project lock
    //     contention.
    //
    // The smoke + behavior + admin + announce specs in `chromium`
    // ARE the user-visible UI surface — those are what we want
    // exercised in Firefox / WebKit nightly.
    projects.push({
        name: 'firefox',
        testIgnore: ['**/critical/**'],
        use: { ...devices['Desktop Firefox'] },
    });
}

if (requestedBrowsers.includes('webkit')) {
    projects.push({
        name: 'webkit',
        testIgnore: ['**/critical/**'],
        use: { ...devices['Desktop Safari'] },
    });
}

if (projects.length === 0) {
    throw new Error(
        `E2E_BROWSERS=${process.env.E2E_BROWSERS ?? ''} did not select any` +
            ' supported browser. Allowed values: chromium, firefox, webkit.',
    );
}

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

    projects,
});
