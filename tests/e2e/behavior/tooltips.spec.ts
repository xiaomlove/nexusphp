import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Behavioural coverage for PR #60 — progressive tooltips on the H&R
 * badge and the user-ratio value.
 *
 * #60 changed two helpers in `include/functions.php`:
 *
 *   - `get_hr_img()` now passes a localised, descriptive string as the
 *     `title=` attribute (was just "H&R"); the `alt` is unchanged.
 *   - `get_user_ratio($html=true)` wraps the rendered ratio in a
 *     `<span class="ratio-tip" title="Share ratio = uploaded /
 *     downloaded …">…</span>` so hovering the colour-coded number in
 *     any header / signature / userdetails reveals what the colour
 *     means and how the ratio is computed.
 *
 * The seeded `e2eadmin` user has uploaded=10 GB / downloaded=5 GB
 * (`Database\Seeders\E2eUsersSeeder` picks these so the ratio is a
 * deterministic 2.0 — green bucket). Without those stats the legacy
 * code returns `---` and the ratio-tip wrapper is skipped entirely,
 * which is why this test depends on `e2e:bootstrap` having seeded
 * the e2e users.
 *
 * What this spec verifies:
 *
 *   1. `/usercp.php` (the topbar appears on every authenticated page,
 *      we pick usercp because it is the cheapest authenticated render)
 *      contains a `<span class="ratio-tip" title="Share ratio …">`
 *      somewhere in the DOM.
 *   2. The span actually wraps a number (so the seeded stats are
 *      flowing through `get_user_ratio()` and not falling through to
 *      `---`).
 */
test.describe('@behavior tooltips on H&R badge + ratio (#60)', () => {
    test('logged-in /usercp.php renders <span class="ratio-tip" title="…">', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.goto('/usercp.php');
        expect(response, 'no response for /usercp.php').not.toBeNull();
        expect(response!.status()).toBe(200);

        const html = await page.content();

        // The wrapper must be present on the page.
        expect(html, 'expected <span class="ratio-tip">').toMatch(
            /<span\s+class=["']ratio-tip["'][^>]*title=["'][^"']*Share ratio[^"']*["']/i,
        );

        // The ratio inside the span must be a real number, not "---".
        // 10 GB / 5 GB == 2.000 with our seeded stats.
        expect(html, 'expected numeric ratio inside .ratio-tip').toMatch(
            /<span[^>]*class=["']ratio-tip["'][^>]*>[\s\S]*?\d+\.\d{3}[\s\S]*?<\/span>/i,
        );
    });
});
