import { request, FullConfig } from '@playwright/test';
import { E2E_USERS } from './fixtures/users';

/**
 * Verifies that the NexusPHP stack is reachable before any test runs.
 *
 * - GET / must return 200 or a 30x redirect (the legacy front controller
 *   redirects to /login.php for unauthenticated visitors)
 * - POST /takelogin.php with the e2eadmin credentials must return 30x
 *   with a `c_secure_pass` cookie (this is what proves
 *   `php artisan e2e:bootstrap` ran and disabled CAPTCHA + challenge-
 *   response auth)
 *
 * If either check fails we throw so the entire run aborts early with a
 * clear error, instead of leaving each spec to fail with a confusing
 * timeout.
 */
async function globalSetup(config: FullConfig) {
    const baseURL =
        config.projects[0]?.use.baseURL ??
        process.env.E2E_BASE_URL ??
        'http://localhost';

    const ctx = await request.newContext({ baseURL, ignoreHTTPSErrors: true });
    try {
        const home = await ctx.get('/', { maxRedirects: 0 });
        if (home.status() < 200 || home.status() >= 400) {
            throw new Error(
                `GET ${baseURL}/ returned HTTP ${home.status()}; ` +
                    `did you run scripts/e2e-stack-up.sh?`,
            );
        }

        const admin = E2E_USERS.admin;
        const login = await ctx.post('/takelogin.php', {
            form: {
                username: admin.username,
                password: admin.password,
            },
            maxRedirects: 0,
        });

        if (login.status() < 300 || login.status() >= 400) {
            throw new Error(
                `POST /takelogin.php as ${admin.username} returned HTTP ` +
                    `${login.status()}; did you run \`php artisan e2e:bootstrap\`? ` +
                    `(see docs/e2e-local.md)`,
            );
        }

        const cookies = await ctx.storageState();
        const hasLoginCookie = cookies.cookies.some(
            (c) => c.name === 'c_secure_pass',
        );
        if (!hasLoginCookie) {
            throw new Error(
                'POST /takelogin.php did not set c_secure_pass cookie; ' +
                    'login flow is broken. Cookies received: ' +
                    cookies.cookies.map((c) => c.name).join(',') ||
                    '(none)',
            );
        }
    } finally {
        await ctx.dispose();
    }
}

export default globalSetup;
