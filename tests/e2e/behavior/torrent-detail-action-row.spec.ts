import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

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
        await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });

        for (const id of ['download-btn', 'action-edit', 'action-report']) {
            const node = page.locator(`[data-test-id="${id}"]`);
            await expect(node).toBeVisible();
            await expect(node, `${id} must be a plain anchor`).toHaveJSProperty('tagName', 'A');
            await expect(node).not.toHaveAttribute('wire:click', /./);
        }
    });

    test('action-row card is absent on /torrent/{id}?legacy=1 (302 to legacy)', async ({ page }) => {
        const response = await page.request.get('/torrent/1?legacy=1', { maxRedirects: 5 });
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).not.toContain('data-test-id="torrent-action-row"');
    });
});
