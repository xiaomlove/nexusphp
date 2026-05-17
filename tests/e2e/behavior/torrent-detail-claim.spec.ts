import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

test.describe('@behavior Modern-UI torrent detail claim block', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'admin');
    });

    test('claim card is either absent or renders with a button on /torrent/{id}', async ({ page }) => {
        const response = await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });
        expect(response, 'no response for /torrent/1').not.toBeNull();
        expect(response!.status(), '/torrent/1 must serve a 200').toBe(200);

        const card = page.locator('[data-test-id="claim-block"]');
        const cardCount = await card.count();

        if (cardCount === 0) {
            return;
        }

        await expect(card).toBeVisible();

        const button = card.locator('[data-test-id="claim-button"]');
        await expect(button, 'claim card must render a claim button').toBeVisible();

        const state = await card.getAttribute('data-claim-state');
        expect(state, 'claim card must expose its state').toMatch(/^(open|claimed)$/);

        const info = card.locator('[data-test-id="claim-info"]');
        await expect(info).toBeVisible();

        const detailLink = card.locator('[data-test-id="claim-detail-link"]');
        await expect(detailLink).toHaveAttribute('href', '/claim.php?torrent_id=1');
    });

    test('claim block is absent on /torrent/{id}?legacy=1 (302 to legacy)', async ({ page }) => {
        const response = await page.request.get('/torrent/1?legacy=1', { maxRedirects: 5 });
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).not.toContain('data-test-id="claim-block"');
    });
});
