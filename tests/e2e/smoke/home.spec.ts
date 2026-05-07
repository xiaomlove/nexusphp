import { expect, test } from '@playwright/test';

test.describe('@smoke unauthenticated home', () => {
    test('GET / redirects an unauthenticated visitor to /login.php', async ({
        request,
    }) => {
        const response = await request.get('/', { maxRedirects: 0 });
        expect(response.status()).toBeGreaterThanOrEqual(300);
        expect(response.status()).toBeLessThan(400);

        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/login\.php/);
    });

    test('GET /login.php renders the login form without JS errors', async ({
        page,
    }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        const response = await page.goto('/login.php');
        expect(response, 'response from /login.php').not.toBeNull();
        expect(response!.status()).toBe(200);

        await expect(page.locator('input[name="username"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();

        // Allow tracker / favicon 404s but no script-level errors.
        const fatal = consoleErrors.filter(
            (e) =>
                !/favicon/i.test(e) &&
                !/sentry/i.test(e) &&
                !/Failed to load resource/i.test(e),
        );
        expect(fatal, `unexpected console errors:\n${fatal.join('\n')}`).toEqual(
            [],
        );
    });
});
