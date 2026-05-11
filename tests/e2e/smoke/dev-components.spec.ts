import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Modern-UI design system — Phase A+D / PR1.
 *
 * `/dev/components` renders an admin-only showcase of every component
 * under `resources/views/components/ui/`. This spec is the regression
 * net for that surface: if a shared component (or the layout that hosts
 * it) breaks, the gallery fails to render and CI catches it in the same
 * run as the change.
 *
 * The route is gated to `CLASS_ADMINISTRATOR` and above, so we exercise
 * three guards:
 *
 *   1. Guest hits `/dev/components` → bounced to `/login.php`
 *      (handled by `auth.nexus:nexus-web` middleware).
 *   2. Regular user hits `/dev/components` → HTTP 403 (the explicit
 *      `abort(403)` inside the controller).
 *   3. Admin hits `/dev/components` → HTTP 200 + every component
 *      section renders.
 *
 * The third case is the meat of the spec — we walk through every
 * `data-section="…"` block the gallery declares so a future component
 * deletion fails *here*, not silently downstream.
 */

const REQUIRED_SECTIONS = [
    'page-header',
    'buttons',
    'badges',
    'form-controls',
    'alerts',
    'stats',
    'cards',
    'empty-state',
    'theme-toggle',
];

test.describe('@smoke Modern-UI components gallery (/dev/components)', () => {
    test('guest is bounced to the legacy login screen', async ({ page }) => {
        const { response, html } = await smokeCheckPage(page, '/dev/components');
        expect(
            response.url(),
            'guest /dev/components should land on a login screen',
        ).toMatch(/login/i);
        expect(html, 'gallery body should NOT be rendered for guests').not.toMatch(
            /data-test-id="components-gallery"/,
        );
    });

    test('regular user gets 403', async ({ page, context }) => {
        await loginAs(context, 'user');

        const response = await page.goto('/dev/components', { waitUntil: 'load' });
        expect(response, 'no response for /dev/components').not.toBeNull();
        expect(
            response!.status(),
            'regular user must NOT see the components gallery',
        ).toBe(403);
    });

    test('admin sees every component section', async ({ page, context }) => {
        await loginAs(context, 'admin');

        const { html, response, consoleErrors } = await smokeCheckPage(
            page,
            '/dev/components',
        );

        expect(response.status(), 'admin should get 200 on /dev/components').toBe(200);
        expect(html, 'gallery root marker missing').toMatch(
            /data-test-id="components-gallery"/,
        );

        for (const section of REQUIRED_SECTIONS) {
            expect(
                html,
                `gallery is missing the "${section}" section — did a component get deleted?`,
            ).toMatch(new RegExp(`data-section="${section}"`));
        }

        // Spot-check that at least one rendered component carries the
        // tokens added by the new Tailwind v4 alias (`primary-`), so a
        // future regression that drops the alias plumbing fails loudly.
        expect(html, 'no primary-* tailwind class rendered').toMatch(/\bbg-primary-\d/);

        expect(consoleErrors).toEqual([]);
    });
});
