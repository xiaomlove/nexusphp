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
    test('GET /torrents.php?legacy=1 exposes #searchinput, #torrents-results and the live-search JS', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // After the Strangler Fig flip, plain /torrents.php 302→/browse.
        // The legacy rendering (with live-search wiring) is accessible
        // via ?legacy=1. We deliberately use `request.get` instead of
        // `page.goto` + `page.content()` here. The browser-driven path
        // proved flaky on PHP's built-in single-threaded webserver (CI):
        // when multiple workers share the server, dependent assets (CSS,
        // JS) can serialise behind the parent HTML response and the
        // page `load` event fires while the body is still streaming —
        // `page.content()` then sees a partial DOM that has the
        // `<head>` but never reached the search-form `<input>` deeper
        // in `<body>`. The raw HTTP response is captured fully on
        // navigation, so this is deterministic.
        const response = await page.request.get('/torrents.php?legacy=1');
        expect(response.status(), 'unexpected status for /torrents.php?legacy=1').toBe(200);

        const html = await response.text();

        expect(html, 'expected #searchinput on /torrents.php?legacy=1').toMatch(
            /id=["']searchinput["']/,
        );
        expect(html, 'expected #torrents-results wrapper on /torrents.php?legacy=1').toMatch(
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

    test('GET /torrents.php?ajax=1 returns a results FRAGMENT (no <html>/<head>) — bypass redirect', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // ?ajax=1 is one of the legacy escape hatches and must NOT be
        // redirected to /browse. request.get goes through the same auth
        // context but doesn't run the page lifecycle / asset loads.
        const response = await page.request.get('/torrents.php?ajax=1');
        expect(response.status(), 'unexpected status for ?ajax=1').toBe(200);

        const body = await response.text();

        expect(body, '?ajax=1 must not contain <html>').not.toMatch(/<html[\s>]/i);
        expect(body, '?ajax=1 must not contain <head>').not.toMatch(/<head[\s>]/i);
        expect(body, '?ajax=1 must not contain </body>').not.toMatch(/<\/body>/i);
    });
});
