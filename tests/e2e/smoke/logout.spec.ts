import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Phase 4 logout flow (PR #30 logout refactor).
 *
 * Logs in as `e2eadmin`, hits `/logout.php`, and asserts that:
 *   - the request resolves to a 2xx after the standard 302 → /login.php
 *     redirect chain (Playwright follows redirects by default);
 *   - no fatal-error markers appear in the body;
 *   - the final landing page is the unauthenticated login form (not a
 *     stack trace or a half-rendered page);
 *   - subsequent visits to a protected legacy page (e.g. `/usercp.php`)
 *     bounce back to login because the session cookie was actually
 *     cleared.
 */
test.describe('@smoke logout flow — phase 4', () => {
    test('logout.php redirects to login and clears session', async ({ page, context }) => {
        await loginAs(context, 'admin');

        const { response, html, consoleErrors } = await smokeCheckPage(page, '/logout.php');
        expect(response.url(), 'logout should land on login page').toMatch(/login\.php/i);
        expect(html, 'login page marker missing after logout').toMatch(/Powered by NexusPHP/i);
        expect(html, 'login form should be rendered').toMatch(/login/i);
        expect(consoleErrors, 'unexpected JS errors on logout').toEqual([]);

        // After logout, the legacy auth cookie should be gone — the next
        // protected page must redirect us back to login (not return the
        // authenticated user control panel).
        const after = await page.goto('/usercp.php');
        expect(after, 'no response for /usercp.php after logout').not.toBeNull();
        expect(after!.url(), '/usercp.php should bounce to login when logged out').toMatch(/login\.php/i);
    });
});
