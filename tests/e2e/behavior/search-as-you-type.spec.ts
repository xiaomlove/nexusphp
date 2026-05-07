import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Behavioural coverage for PR #59 — search-as-you-type on the legacy
 * `/torrents.php` listing.
 *
 * #59 added a new `?ajax=1` mode that emits ONLY the results fragment
 * (no `<html>`, no `<head>`, no header, no footer) so a vanilla-JS
 * handler attached to `<input id="searchinput">` can swap the
 * `<div id="torrents-results">` block in place. The full-page render
 * keeps the same wiring (input id + wrapper div) as a stable target.
 *
 * What this spec verifies (no torrent fixtures required):
 *
 *   1. The full page exposes the AJAX-target wiring:
 *        - `<input id="searchinput">` (the debounce-fired input)
 *        - `<div id="torrents-results">` (the swap target)
 *        - the JS that wires them together (look for `getElementById('searchinput')`
 *          and `?ajax=1` inside an inline <script>).
 *   2. The `?ajax=1` response is a fragment, not a full page:
 *        - no `<html>` tag
 *        - no `<head>` tag
 *        - no `</body>` tag
 *      (an empty result set is an acceptable fragment — the behaviour
 *      under test is "the server emits a fragment", not "the fragment
 *      contains rows".)
 */
test.describe('@behavior search-as-you-type (#59)', () => {
    test('GET /torrents.php exposes #searchinput, #torrents-results and the live-search JS', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.goto('/torrents.php');
        expect(response, 'no response for /torrents.php').not.toBeNull();
        expect(response!.status(), 'unexpected status for /torrents.php').toBe(200);

        const html = await page.content();

        expect(html, 'expected #searchinput on /torrents.php').toMatch(
            /id=["']searchinput["']/,
        );
        expect(html, 'expected #torrents-results wrapper on /torrents.php').toMatch(
            /id=["']torrents-results["']/,
        );
        expect(html, 'expected live-search handler wired to #searchinput').toMatch(
            /getElementById\(\s*['"]searchinput['"]\s*\)/,
        );
        // The handler builds the request URL as `params.set('ajax', '1')`
        // and then `'?' + params.toString()`, so the literal `?ajax=1`
        // never appears in the source — match the underlying call instead.
        expect(html, 'expected live-search handler to set ajax=1 via URLSearchParams').toMatch(
            /set\(\s*['"]ajax['"]\s*,\s*['"]1['"]\s*\)/,
        );
    });

    test('GET /torrents.php?ajax=1 returns a results FRAGMENT (no <html>/<head>)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // request.get goes through the same auth context but doesn't run
        // the page lifecycle / asset loads, so we get the raw bytes the
        // live-search handler would `fetch()` and `innerHTML=`.
        const response = await page.request.get('/torrents.php?ajax=1');
        expect(response.status(), 'unexpected status for ?ajax=1').toBe(200);

        const body = await response.text();

        expect(body, '?ajax=1 must not contain <html>').not.toMatch(/<html[\s>]/i);
        expect(body, '?ajax=1 must not contain <head>').not.toMatch(/<head[\s>]/i);
        expect(body, '?ajax=1 must not contain </body>').not.toMatch(/<\/body>/i);
    });
});
