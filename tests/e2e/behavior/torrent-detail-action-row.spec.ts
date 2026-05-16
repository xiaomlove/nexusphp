import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Phase 3 — Modern UI A3 action row on `/torrent/{id}`. Mirrors the
 * legacy `public/details.php:253-295` action row (Download / Edit /
 * Re-seed / Report) inside the `App\Livewire\TorrentDetail`
 * component. The Approval and Claim buttons stay on the legacy page
 * behind the `?legacy=1` escape hatch and are out of scope for this
 * spec.
 *
 * The seeded admin in `E2eAdminSeeder` owns the seeded torrent
 * (`E2eTorrentsSeeder` sets `torrents.id=1` with `owner=` the same
 * admin row), so the admin viewer also exercises the owner-only
 * "Edit / delete" branch alongside the staff-side `torrentmanage`
 * permission.
 */
test.describe('@behavior Modern-UI torrent detail action row', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'admin');
    });

    test('renders the action-row card with the expected buttons', async ({ page }) => {
        const response = await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });
        expect(response, 'no response for /torrent/1').not.toBeNull();
        expect(response!.status(), '/torrent/1 must serve a 200').toBe(200);

        const card = page.locator('[data-test-id="torrent-action-row"]');
        await expect(card, 'action-row card must render').toBeVisible();

        const download = card.locator('[data-test-id="download-btn"]');
        await expect(download).toBeVisible();
        await expect(download).toHaveAttribute('href', '/download.php?id=1');

        const edit = card.locator('[data-test-id="action-edit"]');
        await expect(edit, 'staff/owner must see edit button').toBeVisible();
        await expect(edit).toHaveAttribute('href', '/edit.php?id=1');

        const report = card.locator('[data-test-id="action-report"]');
        await expect(report).toBeVisible();
        await expect(report).toHaveAttribute('href', '/report.php?torrent=1');
    });

    test('action-row buttons keep the legacy href targets (no JS POSTs)', async ({ page }) => {
        // The action-row is a thin link-out into the legacy PHP
        // handlers. Until those endpoints get migrated (Phase 4 for
        // download.php, separate A3.x waves for edit/takereseed/report),
        // the Modern UI must NOT wire `wire:click` onto these buttons —
        // they have to be plain anchors so deep-linking and copy-link
        // keep working. This guards against a future regression where
        // someone reaches for `wire:click` thinking that's "more modern".
        await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });

        for (const id of ['download-btn', 'action-edit', 'action-report']) {
            const node = page.locator(`[data-test-id="${id}"]`);
            await expect(node).toBeVisible();
            await expect(node, `${id} must be a plain anchor`).toHaveJSProperty('tagName', 'A');
            await expect(node).not.toHaveAttribute('wire:click', /./);
        }
    });

    test('action-row card is absent on /torrent/{id}?legacy=1 (302 to legacy)', async ({ page }) => {
        // The `?legacy=1` escape hatch redirects /torrent/N back to
        // /details.php?id=N&legacy=1, so the Modern UI Livewire
        // component never renders. The action-row test-id therefore
        // must not show up in the response body of the legacy page —
        // that page uses its own `tr($lang_details['row_action'], …)`
        // markup which has no `data-test-id` attributes at all.
        const response = await page.request.get('/torrent/1?legacy=1', { maxRedirects: 5 });
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).not.toContain('data-test-id="torrent-action-row"');
    });
});
