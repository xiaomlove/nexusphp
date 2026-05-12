import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Modern UI A3 — `App\Livewire\TorrentDetail` at `/torrent/{id}`.
 *
 * The spec asserts the four contracts of the new detail page:
 *
 *   1. Guest hits `/torrent/1` → bounced to a login screen (the
 *      `auth.nexus:nexus-web` middleware on the route group).
 *
 *   2. Authenticated admin hits `/torrent/1` → HTTP 200 + the
 *      `data-test-id="torrent-detail"` root marker is rendered.
 *      `E2eTorrentsSeeder` (run via `e2e:bootstrap`) creates the
 *      torrent row with `id=1`.
 *
 *   3. Authenticated admin hits `/torrent/9999999` → HTTP 404.
 *      Missing/banned/invisible torrents abort with 404 inside
 *      `TorrentDetail::mount()`.
 *
 *   4. `/torrent/1?legacy=1` → 302 redirect to
 *      `/details.php?id=1&legacy=1`. This is the Strangler Fig
 *      rollback flag, mirroring `/browse?legacy=1`.
 */
test.describe('@smoke Modern-UI torrent detail (/torrent/{id})', () => {
    test('guest is bounced to the legacy login screen', async ({ page }) => {
        const { response } = await smokeCheckPage(page, '/torrent/1');
        expect(
            response.url(),
            'guest /torrent/1 should land on a login screen',
        ).toMatch(/login/i);
    });

    test('admin sees the modern detail shell with seeded torrent (id=1)', async ({
        page,
        context,
    }) => {
        await loginAs(context, 'admin');

        const { html, response, consoleErrors } = await smokeCheckPage(page, '/torrent/1');

        expect(response.status(), 'admin should get 200 on /torrent/1').toBe(200);
        expect(html, 'detail root marker missing').toMatch(/data-test-id="torrent-detail"/);
        expect(html, 'seeded torrent name not rendered').toContain('E2E Test Torrent');
        expect(html, 'category badge not rendered').toContain('Movies');
        expect(html, 'download CTA missing').toMatch(/data-test-id="download-btn"/);
        expect(html, 'escape-hatch legacy link missing').toMatch(
            /data-test-id="legacy-detail-link"/,
        );

        expect(consoleErrors).toEqual([]);
    });

    test('admin gets 404 for a missing torrent id', async ({ page, context }) => {
        await loginAs(context, 'admin');

        const response = await page.goto('/torrent/9999999', { waitUntil: 'load' });
        expect(response, 'no response for /torrent/9999999').not.toBeNull();
        expect(response!.status(), 'missing torrent must abort 404').toBe(404);
    });

    test('?legacy=1 redirects to /details.php?id=N&legacy=1', async ({ page, context }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrent/1?legacy=1', { maxRedirects: 0 });
        expect(response.status(), 'legacy=1 must 302').toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/details.php?id=1');
        expect(location).toContain('legacy=1');
    });
});
