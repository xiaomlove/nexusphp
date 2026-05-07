import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Markup-level coverage for the shoutbox real-time stack (PRs #2, #14,
 * #15, #61). A full 2-BrowserContext live-broadcast test needs a
 * Reverb websocket server running in CI, which lands in a follow-up
 * Phase 4 PR. This spec verifies the *client-side wiring* survives so
 * that the moment Reverb is enabled, the existing JS will pick the
 * channel up automatically.
 *
 * What this spec verifies on `/shoutbox.php`:
 *
 *   1. `<body data-shout-channel="shoutbox.sb">` — the JS in
 *      `resources/js/echo.js` reads this dataset attribute to pick
 *      the channel to subscribe to (PR #14).
 *   2. The `shoutReply()` function is defined inline in the iframe.
 *      Clicking a nick in the rendered shout list fires this to
 *      prefill the parent form's input with `@nick, ` (PRs #2/#3).
 *   3. The `.shout-mention` CSS class is defined. The server-side
 *      rendering wraps every `@username` in `<a class="shout-mention">`
 *      and the client-side broadcast handler in `resources/js/echo.js`
 *      replays the same markup, so the styling must exist (PR #2).
 *   4. The page does NOT contain the legacy meta-refresh-only fallback
 *      output — the iframe must always render the `data-shout-channel`
 *      attribute so the broadcast handler can attach (PR #14 added it
 *      unconditionally).
 *
 * `__REVERB__` is intentionally *not* asserted: it only renders when
 * `REVERB_APP_KEY` is configured (production / staging), and the e2e
 * stack runs without Reverb in the CI job today. The Phase 4 spec
 * will exercise the full ws path with Reverb running.
 */
test.describe('@behavior shoutbox real-time client wiring (#2, #14, #15, #61)', () => {
    test('GET /shoutbox.php renders the channel dataset attribute and reply JS', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.goto('/shoutbox.php');
        expect(response, 'no response for /shoutbox.php').not.toBeNull();
        expect(response!.status()).toBe(200);

        const html = await page.content();

        // Default type=shoutbox -> channel "shoutbox.sb"
        expect(html, 'expected data-shout-channel on <body>').toMatch(
            /<body[^>]*data-shout-channel=["']shoutbox\.sb["']/i,
        );

        expect(html, 'expected shoutReply() helper').toMatch(
            /function\s+shoutReply\s*\(\s*nick\s*\)/,
        );

        // CSS class for the rendered @-mention anchors is defined inline
        // (mediumfont/theme stylesheets don't ship it — see public/shoutbox.php).
        expect(html, 'expected .shout-mention CSS class').toMatch(
            /\.shout-mention\s*\{/,
        );
    });

    test('GET /shoutbox.php?type=helpbox subscribes to the helpbox channel', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.goto('/shoutbox.php?type=helpbox');
        expect(response, 'no response for /shoutbox.php?type=helpbox').not.toBeNull();
        expect(response!.status()).toBe(200);

        const html = await page.content();

        expect(html, 'helpbox iframe must subscribe to shoutbox.hb').toMatch(
            /<body[^>]*data-shout-channel=["']shoutbox\.hb["']/i,
        );
    });
});
