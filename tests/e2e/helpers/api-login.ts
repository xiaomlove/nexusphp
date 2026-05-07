import { APIRequestContext, BrowserContext, request } from '@playwright/test';
import { E2E_USERS, E2eUserRole } from '../fixtures/users';

/**
 * Login without driving the browser. Calls `POST /takelogin.php`,
 * collects the `Set-Cookie` headers, and adds them to the supplied
 * `BrowserContext`. After this returns the next `page.goto(...)` is
 * authenticated as the given role.
 *
 * This skips the entire login form so each spec only spends ~150 ms on
 * auth instead of clicking through a JS-rendered page.
 *
 * Requires `php artisan e2e:bootstrap` to have run (it disables
 * `security.iv` and `security.use_challenge_response_authentication`,
 * which otherwise break a plain HTTP POST).
 */
export async function loginAs(
    context: BrowserContext,
    role: E2eUserRole,
    baseURL?: string,
): Promise<void> {
    const url =
        baseURL ?? process.env.E2E_BASE_URL ?? 'http://localhost';
    const user = E2E_USERS[role];

    const apiCtx: APIRequestContext = await request.newContext({
        baseURL: url,
        ignoreHTTPSErrors: true,
    });

    try {
        const response = await apiCtx.post('/takelogin.php', {
            form: {
                username: user.username,
                password: user.password,
            },
            maxRedirects: 0,
        });

        if (response.status() < 300 || response.status() >= 400) {
            throw new Error(
                `loginAs(${role}): POST /takelogin.php returned HTTP ` +
                    `${response.status()} (expected 30x). Body: ` +
                    (await response.text()).slice(0, 500),
            );
        }

        const state = await apiCtx.storageState();
        await context.addCookies(state.cookies);
    } finally {
        await apiCtx.dispose();
    }
}
