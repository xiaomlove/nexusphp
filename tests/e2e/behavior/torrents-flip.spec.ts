import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Strangler Fig flip (Phase 3.3 / Modern UI plan A2) — `/torrents.php`
 * now 302→`/browse` by default, with two escape hatches that keep the
 * legacy page reachable:
 *
 *   - `?legacy=1` — explicit opt-out
 *   - `?ajax=1`   — search-as-you-type fragment endpoint (PR #59)
 *
 * `?bookmarks=1` now flips to `/browse?inclbookmarked=only` (the
 * Livewire `TorrentBrowse` already exposes `BOOKMARK_ONLY` via that
 * param).
 *
 * These tests verify the redirect contract, param translation, and
 * escape-hatch paths.
 */
test.describe('@behavior Strangler Fig flip: /torrents.php → /browse', () => {
    test('plain /torrents.php 302→/browse', async ({ context, page }) => {
        await loginAs(context, 'admin');

        // maxRedirects:0 prevents Playwright from following the 302.
        const response = await page.request.get('/torrents.php', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/browse$/);
    });

    test('/torrents.php?search=test preserves query string', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrents.php?search=test', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/browse?');
        expect(location).toContain('search=test');
    });

    test('/torrents.php?cat=5 translates to /browse?category=5', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrents.php?cat=5', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('category=5');
        expect(location).not.toContain('cat=');
    });

    test('/torrents.php?incldead=1 translates to /browse?incldead=dead', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrents.php?incldead=1', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('incldead=dead');
    });

    test('/torrents.php?legacy=1 stays on legacy (no redirect)', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrents.php?legacy=1');
        expect(response.status()).toBe(200);
    });

    test('/torrents.php?ajax=1 stays on legacy (AJAX escape hatch)', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrents.php?ajax=1');
        expect(response.status()).toBe(200);
    });

    test('/torrents.php?bookmarks=1 → /browse?inclbookmarked=only', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/torrents.php?bookmarks=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/browse?');
        expect(location).toContain('inclbookmarked=only');
        expect(location).not.toContain('bookmarks=');
    });

    test('/torrents.php?bookmarks=1&search=foo preserves other params', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get(
            '/torrents.php?bookmarks=1&search=foo',
            { maxRedirects: 0 },
        );
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('inclbookmarked=only');
        expect(location).toContain('search=foo');
        expect(location).not.toContain('bookmarks=');
    });
});
