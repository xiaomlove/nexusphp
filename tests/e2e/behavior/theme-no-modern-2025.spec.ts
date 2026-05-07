import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Negative coverage for PR #62 — the revert of the "Modern 2025"
 * theme that landed in PR #9 and was reverted before it shipped.
 *
 * This spec is the regression guard: if a future merge accidentally
 * re-applies the Modern 2025 theme — directly or via merge of an old
 * branch — the test fails immediately. There is no positive marker we
 * can check (the theme was reverted, so nothing was added in its
 * place), only the *absence* of a known set of identifiers.
 *
 * Identifiers to NOT see anywhere on a default-skin page:
 *
 *   - `theme-modern-2025` class on `<html>` / `<body>` / wrapping div
 *   - `<link href=".*modern-?2025.*\.css">` stylesheet reference
 *   - `Modern 2025` user-facing label (would appear in a theme picker)
 *
 * We sample three rendered surfaces — the legacy home, the Livewire
 * `/browse` page, and the user control panel — because the theme
 * change in #9 touched the global stdhead/stdfoot blocks that are
 * shared across all three render paths. Hitting all three protects
 * against a re-introduction that touches only one of them.
 */
const MODERN_2025_PATTERNS: Array<{ pattern: RegExp; description: string }> = [
    {
        pattern: /class=["'][^"']*\btheme-modern-2025\b[^"']*["']/i,
        description: '"theme-modern-2025" CSS class',
    },
    {
        pattern: /<link[^>]+href=["'][^"']*modern-?2025[^"']*\.css["']/i,
        description: '<link rel="stylesheet" …modern-2025…>',
    },
    {
        pattern: /Modern\s+2025/i,
        description: '"Modern 2025" user-facing label',
    },
];

const PAGES = ['/index.php', '/browse', '/usercp.php'];

test.describe('@behavior negative: Modern 2025 theme NOT applied (#62)', () => {
    for (const url of PAGES) {
        test(`${url} contains no Modern 2025 identifiers`, async ({ context, page }) => {
            await loginAs(context, 'admin');

            const response = await page.goto(url);
            expect(response, `no response for ${url}`).not.toBeNull();
            expect(response!.status()).toBeLessThan(400);

            const html = await page.content();

            for (const { pattern, description } of MODERN_2025_PATTERNS) {
                expect(
                    html,
                    `${url} must not contain ${description} (revert PR #62)`,
                ).not.toMatch(pattern);
            }
        });
    }
});
