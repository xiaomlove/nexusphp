import { expect, test } from '@playwright/test';
import { E2E_USERS } from '../fixtures/users';
import { loginAs } from '../helpers/api-login';

test.describe('@smoke legacy login', () => {
    test('POST /takelogin.php with valid credentials sets c_secure_pass', async ({
        request,
    }) => {
        const admin = E2E_USERS.admin;
        const response = await request.post('/takelogin.php', {
            form: {
                username: admin.username,
                password: admin.password,
            },
            maxRedirects: 0,
        });

        expect(response.status()).toBeGreaterThanOrEqual(300);
        expect(response.status()).toBeLessThan(400);

        // Playwright exposes Set-Cookie via the request context.
        const setCookie = response.headers()['set-cookie'] ?? '';
        expect(setCookie).toMatch(/c_secure_pass=/);
    });

    test('POST /takelogin.php with an unknown user does NOT set c_secure_pass', async ({
        request,
    }) => {
        const response = await request.post('/takelogin.php', {
            form: {
                username: 'no_such_user_e2e',
                password: 'wrong',
            },
            maxRedirects: 0,
        });

        const setCookie = response.headers()['set-cookie'] ?? '';
        expect(setCookie).not.toMatch(/c_secure_pass=[^;]/);
    });

    test('after loginAs(admin), /usercp.php returns 200 and shows e2eadmin', async ({
        page,
        context,
    }) => {
        await loginAs(context, 'admin');
        const response = await page.goto('/usercp.php');
        expect(response).not.toBeNull();
        expect(response!.status()).toBe(200);

        const html = await page.content();
        expect(html.toLowerCase()).toContain('e2eadmin');
    });

    test('after loginAs(user), /usercp.php returns 200 and shows e2euser', async ({
        page,
        context,
    }) => {
        await loginAs(context, 'user');
        const response = await page.goto('/usercp.php');
        expect(response).not.toBeNull();
        expect(response!.status()).toBe(200);

        const html = await page.content();
        expect(html.toLowerCase()).toContain('e2euser');
    });
});
