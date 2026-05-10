import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Phase 5 — Horizon admin gate (PR #13).
 *
 * Horizon is the Laravel queue dashboard, mounted at `/horizon`. PR #13
 * gated it behind admin authentication; we verify both halves of that
 * gate.
 *
 * Coverage:
 *   - Guest hits `/horizon` and is redirected to a login page (the
 *     legacy `/login.php` for non-Filament guests, or `/nexusphp/login`
 *     for Filament). Either way the final URL must contain `login`.
 *   - Admin hits `/horizon` and gets a 200 with the Horizon SPA shell.
 *
 * We do NOT cover Telescope (#12) here because Telescope is not
 * registered in this install (`/telescope` returns 404).
 */
test.describe('@admin Horizon admin gate — phase 5', () => {
    test('guest /horizon bounces to a login page', async ({ page }) => {
        const { response, html, consoleErrors } = await smokeCheckPage(page, '/horizon');
        expect(response.url(), 'guest /horizon should land on a login screen').toMatch(/login/i);
        expect(html, 'guest /horizon should NOT show the Horizon dashboard').not.toMatch(/<title>Horizon/i);
        expect(consoleErrors).toEqual([]);
    });

    test('admin /horizon renders the Horizon SPA shell', async ({ page, context }) => {
        await loginAs(context, 'admin');
        const { html, response, consoleErrors } = await smokeCheckPage(page, '/horizon');
        expect(response.status(), 'admin should get 200 on /horizon').toBe(200);
        // Horizon ships a fixed `<title>Horizon - …</title>` and mounts a
        // `<div id="horizon">` root; require the title to confirm the
        // dashboard actually loaded.
        expect(html, 'Horizon title missing').toMatch(/<title>Horizon/i);
        expect(consoleErrors).toEqual([]);
    });
});
