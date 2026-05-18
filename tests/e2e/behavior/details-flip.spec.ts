import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Strangler Fig flip (Phase 3.x) — `/details.php?id=N` now 302→
 * Livewire `/torrent/{id}` (App\Livewire\TorrentDetail) by default.
 * Plain `?id=N` GETs flip; anything carrying an escape-hatch param or
 * a non-GET method falls through to legacy.
 *
 * Escape hatches that stay on legacy:
 *
 *   - `?legacy=1` — explicit canary opt-out
 *   - `?cmtpage=N` — comments pagination (no Livewire equivalent yet)
 *   - `?uploaded` / `?edited` / `?existed` (+ optional `?returnto`) —
 *                   post-write success banners after upload / edit
 *   - non-GET methods — inline action POSTs (?subtitleupload, …)
 *
 * These tests verify the redirect contract and that the legacy
 * fall-through paths still return 2xx.
 */
test.describe('@behavior Strangler Fig flip: /details.php → /torrent/{id}', () => {
    test('/details.php?id=1 → /torrent/1', async ({ context, page }) => {
        await loginAs(context, 'admin');

        // maxRedirects:0 prevents Playwright from following the 302.
        const response = await page.request.get('/details.php?id=1', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/torrent\/1$/);
    });

    test('/details.php?id=1 preserves extra query params', async ({ context, page }) => {
        await loginAs(context, 'admin');

        // Extra params (e.g. analytics tags) are forwarded verbatim;
        // TorrentDetail ignores keys it does not know.
        const response = await page.request.get('/details.php?id=1&ref=newsletter', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/torrent/1?');
        expect(location).toContain('ref=newsletter');
        // The legacy `id=` key is consumed by the route path, not
        // forwarded as a query string.
        expect(location).not.toMatch(/[?&]id=/);
    });

    test('/details.php?id=1&legacy=1 stays on legacy (canary)', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/details.php?id=1&legacy=1');
        expect(response.status()).toBe(200);
        const body = await response.text();
        // Legacy page renders the seeded torrent name from
        // E2eTorrentsSeeder.
        expect(body).toContain('E2E Test Torrent');
    });

    test('/details.php?id=1&hit=1 → /torrent/1?hit=1 (view counter on Livewire)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // First-party "open from listing" link with view-counter side
        // effect — the counter is now wired into `TorrentDetail::mount()`,
        // so these flip cleanly and the `?hit=1` flag is preserved as a
        // query param on the new canonical URL.
        const response = await page.request.get('/details.php?id=1&hit=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/torrent/1?');
        expect(location).toContain('hit=1');
        expect(location).not.toMatch(/[?&]id=/);
    });

    test('/details.php?id=1&cmtpage=2 stays on legacy (comments pagination)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // Comments pagination has no Livewire equivalent yet, so
        // ?cmtpage=N must fall through to the legacy page.
        const response = await page.request.get('/details.php?id=1&cmtpage=2', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(200);
    });

    test('/details.php?id=1&dllist=1 → /torrent/1?dllist=1 (peer list rendered inline)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // The legacy `?dllist=1` flag auto-triggered the AJAX peer-list
        // popup. The Modern UI renders the peer list server-side as
        // part of the page, so the flag is a no-op there — we still
        // flip the redirect so listings' `#seeders` / `#leechers`
        // fragment links resolve against the new canonical URL.
        const response = await page.request.get('/details.php?id=1&dllist=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/torrent/1?');
        expect(location).toContain('dllist=1');
        expect(location).not.toMatch(/[?&]id=/);
    });

    test('/details.php?id=1&uploaded=1 stays on legacy (post-write banner hatch)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/details.php?id=1&uploaded=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(200);
    });

    test('/details.php?id=1&edited=1&returnto=/index.php stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // ?edited=1 is paired with ?returnto=URL on the legacy
        // "after edit" banner — both must stay on legacy together so
        // the "go back" link still renders.
        const response = await page.request.get(
            '/details.php?id=1&edited=1&returnto=%2Findex.php',
            { maxRedirects: 0 },
        );
        expect(response.status()).toBe(200);
    });

    test('/details.php?id=1&existed=1 stays on legacy (dupe-upload banner hatch)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/details.php?id=1&existed=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(200);
    });

    test('POST /details.php?id=1 stays on legacy (inline action POSTs)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // The flip block only fires on GET; POST submissions (e.g.
        // ?subtitleupload) must fall through to the legacy handler.
        // We don't post a body here — the legacy file rejects the
        // empty submission with a 2xx user-visible error, which is
        // sufficient to prove the flip block did not 302 it.
        const response = await page.request.post('/details.php?id=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() >= 300 && response.status() < 400) {
            const location = response.headers()['location'] ?? '';
            // A POST must never get the read-side flip to /torrent/{id}.
            expect(location).not.toMatch(/^\/torrent\/\d+/);
        }
    });

    test('/details.php without id stays on legacy (legacy "missing id" error)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // Without `id`, the flip cannot construct a clean target —
        // request must fall through to the legacy handler which
        // emits its own user-visible error.
        const response = await page.request.get('/details.php', { maxRedirects: 0 });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() >= 300 && response.status() < 400) {
            const location = response.headers()['location'] ?? '';
            expect(location).not.toMatch(/^\/torrent\//);
        }
    });
});
