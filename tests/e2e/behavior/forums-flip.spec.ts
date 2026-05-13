import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Strangler Fig flip (Phase 3.x) — `/forums.php` now 302→Livewire
 * forum routes by default. Read-only browsing and compose-form
 * GETs that have a Livewire equivalent flip; mutating actions and
 * the ones still missing a replacement fall through to legacy.
 *
 * Routes the redirect targets:
 *
 *   - `/forums.php`                         → `/forum`         (ForumIndex)
 *   - `/forums.php?action=viewforum&forumid=N` → `/forum/N`    (ForumView)
 *   - `/forums.php?action=newtopic&forumid=N`  → `/forum/N/new` (NewTopicForm)
 *
 * Escape hatches that stay on legacy:
 *
 *   - `?legacy=1` — explicit canary opt-out
 *   - every other `action=…` value (reply / quotepost / editpost /
 *     post / viewtopic / viewunread / search / movetopic /
 *     deletetopic / deletepost / setlocked / hltopic / setsticky)
 *
 * These tests verify the redirect contract and that the legacy
 * fall-through paths still return 2xx.
 */
test.describe('@behavior Strangler Fig flip: /forums.php → /forum', () => {
    test('plain /forums.php 302→/forum', async ({ context, page }) => {
        await loginAs(context, 'admin');

        // maxRedirects:0 prevents Playwright from following the 302.
        const response = await page.request.get('/forums.php', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/forum$/);
    });

    test('/forums.php?action=viewforum&forumid=1 → /forum/1', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=viewforum&forumid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/forum\/1$/);
    });

    test('/forums.php?action=viewforum preserves extra query params', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get(
            '/forums.php?action=viewforum&forumid=1&sort=lastpostdesc',
            { maxRedirects: 0 },
        );
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/forum/1?');
        expect(location).toContain('sort=lastpostdesc');
        // The legacy `action=` and `forumid=` keys are consumed by the
        // route path, not forwarded as query string.
        expect(location).not.toContain('action=');
        expect(location).not.toContain('forumid=');
    });

    test('/forums.php?action=newtopic&forumid=1 → /forum/1/new', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=newtopic&forumid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/forum\/1\/new$/);
    });

    test('/forums.php?action=viewforum without forumid stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // No forumid → the redirect cannot construct a clean
        // `/forum/{N}` URL, so the request must fall through to the
        // legacy "forum not found" handler rather than 404'ing in
        // Laravel route model binding.
        const response = await page.request.get('/forums.php?action=viewforum', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
    });

    test('/forums.php?legacy=1 stays on legacy (canary)', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?legacy=1');
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).toMatch(/NexusPHP\s*::\s*Forums/i);
    });

    test('/forums.php?action=viewtopic&topicid=N → /forum/topic/N (Laravel resolver)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=viewtopic&topicid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        // First hop: legacy → bare-topic-ID shortcut route. The
        // shortcut route then looks up `forumid` and re-redirects to
        // /forum/{forumid}/topic/{topic} (validated below).
        expect(location).toMatch(/\/forum\/topic\/1$/);
    });

    test('/forum/topic/N → /forum/{forumid}/topic/N', async ({ context, page }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forum/topic/1', { maxRedirects: 0 });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        // The exact forumid depends on seed data — we only assert the
        // shape, not the specific forum.
        expect(location).toMatch(/^\/forum\/\d+\/topic\/1$/);
    });

    test('/forums.php?action=viewtopic without topicid stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // No topicid → the redirect cannot construct a clean
        // `/forum/topic/{N}` URL, so the request must fall through to
        // the legacy "topic not found" handler.
        const response = await page.request.get('/forums.php?action=viewtopic', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() === 302) {
            const location = response.headers()['location'] ?? '';
            expect(location).not.toMatch(/^\/forum\//);
        }
    });

    test('/forums.php?action=reply stays on legacy (compose-form escape hatch)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=reply&topicid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() === 302) {
            const location = response.headers()['location'] ?? '';
            expect(location).not.toMatch(/^\/forum\//);
        }
    });
});
