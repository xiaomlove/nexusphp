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

    test('renders Phase 3.2 range inputs and sub-category dropdowns', async ({ page, context }) => {
        await loginAs(context, 'admin');
        const { html } = await smokeCheckPage(page, '/browse');

        // The advanced-filters disclosure container is present.
        expect(html).toMatch(/data-testid="advanced-filters"/);

        // Range inputs (size, seeders, leechers, snatches — min + max).
        expect(html).toMatch(/id="size_begin"/);
        expect(html).toMatch(/id="size_end"/);
        expect(html).toMatch(/id="seeders_begin"/);
        expect(html).toMatch(/id="seeders_end"/);
        expect(html).toMatch(/id="leechers_begin"/);
        expect(html).toMatch(/id="leechers_end"/);
        expect(html).toMatch(/id="times_completed_begin"/);
        expect(html).toMatch(/id="times_completed_end"/);

        // Sub-category dropdowns.
        expect(html).toMatch(/id="subcat_source"/);
        expect(html).toMatch(/id="subcat_medium"/);
        expect(html).toMatch(/id="subcat_codec"/);
        expect(html).toMatch(/id="subcat_standard"/);
        expect(html).toMatch(/id="subcat_processing"/);
        expect(html).toMatch(/id="subcat_team"/);
        expect(html).toMatch(/id="subcat_audiocodec"/);
    });

    test('range filter ?size_begin survives a reload via URL binding', async ({ page, context }) => {
        await loginAs(context, 'admin');
        const response = await page.goto('/browse?size_begin=4');
        expect(response, 'no response for /browse?size_begin=4').not.toBeNull();

        // URL is preserved on the client (Livewire's #[Url] round-trip).
        // The functional behavior (the SQL predicate, the input value
        // being hydrated from the query string) is asserted in the
        // PHPUnit Feature suite; here we just smoke-test that the
        // route mounts the component with an `?size_begin=…` param.
        expect(page.url()).toMatch(/size_begin=4/);

        // Component still renders (no Blade compile-time blow-up).
        const html = await page.content();
        expect(html).toMatch(/data-testid="advanced-filters"/);
    });

    test('?legacy=1 redirects to torrents.php?legacy=1 (stays on legacy)', async ({ page, context }) => {
        await loginAs(context, 'admin');
        const response = await page.goto('/browse?legacy=1&sort=newest');

        expect(response, 'no response for /browse?legacy=1').not.toBeNull();

        // After the Strangler Fig flip, /browse?legacy=1 must land on
        // /torrents.php?legacy=1 (the escape hatch that keeps the user
        // on legacy — without it, /torrents.php 302→/browse would loop).
        expect(page.url(), 'expected to land on /torrents.php').toMatch(/\/torrents\.php/);
        expect(page.url(), 'expected sort=newest to survive the hop').toMatch(/sort=newest/);
        expect(page.url(), 'expected legacy=1 to survive so the flip does not bounce back').toMatch(/legacy=1/);
    });
});
