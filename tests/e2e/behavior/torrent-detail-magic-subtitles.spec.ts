import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

test.describe('@behavior Modern-UI torrent detail magic + subtitles', () => {
    test.beforeEach(async ({ context }) => {
        await loginAs(context, 'admin');
    });

    test('renders the subtitles card on /torrent/{id}', async ({ page }) => {
        const response = await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });
        expect(response, 'no response for /torrent/1').not.toBeNull();
        expect(response!.status(), '/torrent/1 must serve a 200').toBe(200);

        const card = page.locator('[data-test-id="subtitles-block"]');
        await expect(card, 'subtitles card must render').toBeVisible();
        await expect(card).toHaveAttribute('data-subtitles-count', /^\d+$/);
    });

    test('subtitles upload form is reachable for the owner / member', async ({ page }) => {
        await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });

        const form = page.locator('[data-test-id="subtitles-upload-form"]');
        await expect(form, 'upload form must render for permitted viewer').toBeVisible();
        await expect(form).toHaveAttribute('action', '/subtitles.php');

        const button = form.locator('[data-test-id="subtitles-upload-button"]');
        await expect(button).toBeVisible();
    });

    test('renders the magic card on /torrent/{id}', async ({ page }) => {
        await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });

        const card = page.locator('[data-test-id="magic-block"]');
        await expect(card, 'magic card must render').toBeVisible();
        await expect(card).toHaveAttribute('data-magic-unique-users', /^\d+$/);
        await expect(card).toHaveAttribute('data-magic-total-value', /^\d+$/);
        await expect(card).toHaveAttribute('data-magic-has-given', /^(yes|no)$/);
    });

    test('magic option buttons emit wire:click="addMagic(...)"', async ({ page }) => {
        await page.goto('/torrent/1', { waitUntil: 'domcontentloaded' });

        const options = page.locator('[data-test-id="magic-option-button"]');
        const count = await options.count();
        if (count === 0) {
            test.skip(true, 'admin already gave / is owner / has insufficient bonus on /torrent/1');
        }
        const first = options.first();
        await expect(first).toBeVisible();
        await expect(first).toHaveAttribute('wire:click', /^addMagic\(\d+\)$/);
        await expect(first).toHaveAttribute('data-magic-value', /^\d+$/);
    });

    test('magic + subtitles cards are absent on /torrent/{id}?legacy=1 (302 to legacy)', async ({ page }) => {
        const response = await page.request.get('/torrent/1?legacy=1', { maxRedirects: 5 });
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).not.toContain('data-test-id="magic-block"');
        expect(body).not.toContain('data-test-id="subtitles-block"');
    });
});
