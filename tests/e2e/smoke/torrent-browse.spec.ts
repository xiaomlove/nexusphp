import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Phase 3 spike coverage for the extended Livewire `TorrentBrowse`
 * component (the canary that mirrors `public/torrents.php`).
 *
 * The functional contract is verified by the PHPUnit Feature suite in
 * `tests/Feature/Livewire/TorrentBrowseTest.php`; this spec just makes
 * sure the page renders the new filter widgets and the `?legacy=1`
 * fallback hop works end-to-end, so we notice fast if Blade syntax,
 * routing, or middleware regress.
 */
test.describe('@smoke /browse (Phase 3 spike)', () => {
    test('renders the new Phase 3 filter widgets', async ({ page, context }) => {
        await loginAs(context, 'admin');
        const { html } = await smokeCheckPage(page, '/browse');

        // The component is wired up.
        expect(html).toMatch(/wire:id=/);
        expect(html).toMatch(/Browse torrents/i);

        // Every Phase 3 filter dropdown should render.
        expect(html).toMatch(/id="spstate"/);
        expect(html).toMatch(/id="incldead"/);
        expect(html).toMatch(/id="tag_id"/);

        // Bookmark filter is gated to authenticated users; admin is auth'd.
        expect(html).toMatch(/id="inclbookmarked"/);

        // The "back to legacy" canary link.
        expect(html).toMatch(/href="[^"]*\/browse\?legacy=1"/);
    });

    test('?legacy=1 redirects to torrents.php', async ({ page, context }) => {
        await loginAs(context, 'admin');
        const response = await page.goto('/browse?legacy=1&sort=newest');

        expect(response, 'no response for /browse?legacy=1').not.toBeNull();

        // Either the browser already followed the redirect into
        // torrents.php (final URL contains /torrents.php) or we received
        // a real 30x with the redirect target. Both are valid because
        // Playwright follows redirects by default.
        expect(page.url(), 'expected to land on /torrents.php').toMatch(/\/torrents\.php/);
        expect(page.url(), 'expected sort=newest to survive the hop').toMatch(/sort=newest/);
        expect(page.url(), 'expected legacy= to be stripped from URL').not.toMatch(/legacy=/);
    });
});
