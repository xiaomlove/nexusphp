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
 *   - every other `action=…` value (post / movetopic / deletepost /
 *     setlocked / hltopic / setsticky)
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

    test('/forum/topic/N resolves topic → forum or 404 (Laravel resolver contract)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // The CI seed only creates forums (via E2eBootstrap), not
        // topics. The resolver contract is: existing topic → 302 to
        // /forum/{forumid}/topic/{id}; missing topic → 404. We exercise
        // the route with both an "id likely to exist if any topics
        // exist" and an "id we know cannot exist", and accept either
        // outcome for the first probe — what matters is that the route
        // never 500s and produces a canonical-shaped location header
        // when it redirects.
        const probe = await page.request.get('/forum/topic/1', { maxRedirects: 0 });
        expect([302, 404]).toContain(probe.status());
        if (probe.status() === 302) {
            const location = probe.headers()['location'] ?? '';
            expect(location).toMatch(/^\/forum\/\d+\/topic\/1(\?.*)?$/);
        }

        // Known-missing id must always 404 (never 500, never silently
        // redirect to a bogus forum).
        const missing = await page.request.get('/forum/topic/999999999', {
            maxRedirects: 0,
        });
        expect(missing.status()).toBe(404);

        // Non-numeric segment must be rejected by `->whereNumber('topic')`
        // before reaching the controller.
        const nonNumeric = await page.request.get('/forum/topic/abc', {
            maxRedirects: 0,
        });
        expect(nonNumeric.status()).toBe(404);
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

    test('/forums.php?action=reply&topicid=N → /forum/topic/N?compose=reply (legacy hop)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=reply&topicid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        // First hop: legacy → bare-topic-ID shortcut with the
        // `compose=reply` marker that the resolver turns into a
        // `#reply` fragment on the canonical URL.
        expect(location).toMatch(/\/forum\/topic\/1\?compose=reply$/);
    });

    test('/forum/topic/N?compose=reply → /forum/{forumid}/topic/N#reply', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // Second hop: the bare-topic-ID resolver looks up `forumid`,
        // strips the `compose=reply` marker, and adds a `#reply` URL
        // fragment so the browser scrolls to the inline ReplyForm.
        const probe = await page.request.get('/forum/topic/1?compose=reply', {
            maxRedirects: 0,
        });
        expect([302, 404]).toContain(probe.status());
        if (probe.status() === 302) {
            const location = probe.headers()['location'] ?? '';
            expect(location).toMatch(/^\/forum\/\d+\/topic\/1#reply$/);
        }
    });

    test('/forums.php?action=reply without topicid stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // No topicid → the redirect cannot construct a clean target
        // URL, so the request must fall through to the legacy "topic
        // not found" handler.
        const response = await page.request.get('/forums.php?action=reply', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() === 302) {
            const location = response.headers()['location'] ?? '';
            expect(location).not.toMatch(/^\/forum\//);
        }
    });

    test('/forums.php?action=quotepost&postid=N → /forum/post/N?compose=quote', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // First hop: legacy → bare-post-ID shortcut with the
        // `compose=quote` marker that the resolver turns into a
        // `?quote=N#reply` query+fragment on the canonical URL.
        const response = await page.request.get('/forums.php?action=quotepost&postid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/forum\/post\/1\?compose=quote$/);
    });

    test('/forums.php?action=editpost&postid=N → /forum/post/N?compose=edit', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // First hop: legacy → bare-post-ID shortcut with the
        // `compose=edit` marker that the resolver turns into a
        // `?edit=N#post-N` query+fragment on the canonical URL.
        const response = await page.request.get('/forums.php?action=editpost&postid=1', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toMatch(/\/forum\/post\/1\?compose=edit$/);
    });

    test('/forum/post/N resolves post → topic+forum or 404 (Laravel resolver contract)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // Second hop with compose=quote: resolver looks up
        // `post → topic → forum`, strips the marker, and emits a
        // `?quote=N#reply` query+fragment so the inline ReplyForm
        // prefills with the quoted body.
        const quoteProbe = await page.request.get('/forum/post/1?compose=quote', {
            maxRedirects: 0,
        });
        expect([302, 404]).toContain(quoteProbe.status());
        if (quoteProbe.status() === 302) {
            const location = quoteProbe.headers()['location'] ?? '';
            expect(location).toMatch(/^\/forum\/\d+\/topic\/\d+\?quote=1#reply$/);
        }

        // Second hop with compose=edit: resolver emits a
        // `?edit=N#post-N` query+fragment so TopicView pre-opens the
        // inline EditPostForm at the right post.
        const editProbe = await page.request.get('/forum/post/1?compose=edit', {
            maxRedirects: 0,
        });
        expect([302, 404]).toContain(editProbe.status());
        if (editProbe.status() === 302) {
            const location = editProbe.headers()['location'] ?? '';
            expect(location).toMatch(/^\/forum\/\d+\/topic\/\d+\?edit=1#post-1$/);
        }

        // Known-missing post id → must 404.
        const missing = await page.request.get('/forum/post/999999999', { maxRedirects: 0 });
        expect(missing.status()).toBe(404);

        // Non-numeric segment → router constraint rejects it before
        // the controller runs.
        const nonNumeric = await page.request.get('/forum/post/abc', { maxRedirects: 0 });
        expect(nonNumeric.status()).toBe(404);
    });

    test('/forums.php?action=quotepost without postid stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=quotepost', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() === 302) {
            const location = response.headers()['location'] ?? '';
            expect(location).not.toMatch(/^\/forum\//);
        }
    });

    test('/forums.php?action=editpost without postid stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=editpost', {
            maxRedirects: 0,
        });
        expect(response.status()).toBeGreaterThanOrEqual(200);
        expect(response.status()).toBeLessThan(400);
        if (response.status() === 302) {
            const location = response.headers()['location'] ?? '';
            expect(location).not.toMatch(/^\/forum\//);
        }
    });

    test('/forums.php?action=viewunread → /forum/unread (legacy hop)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=viewunread', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        // Single-hop: legacy → Livewire ForumUnread. No DB lookup
        // before Laravel boots — the redirect runs in the pre-bootstrap
        // block at the top of public/forums.php.
        expect(location).toBe('/forum/unread');
    });

    test('/forums.php?action=viewunread preserves the beforepostid cursor', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // The legacy "Show more" link rewinds further back with
        // `beforepostid=N`. The Livewire ForumUnread component accepts
        // the same query param verbatim (via `#[Url(as: 'beforepostid')]`),
        // so the redirect must preserve it.
        const response = await page.request.get(
            '/forums.php?action=viewunread&beforepostid=12345',
            { maxRedirects: 0 },
        );
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toBe('/forum/unread?beforepostid=12345');
    });

    test('/forum/unread renders the Livewire ForumUnread component', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        // The page must render without error for an authenticated user.
        // We do not assert the unread list content (seed has no
        // readposts rows), only that the empty-state heading is
        // present, confirming the component mounted.
        const response = await page.goto('/forum/unread');
        expect(response).not.toBeNull();
        expect([200, 302]).toContain(response!.status());
        if (response!.status() === 200) {
            await expect(page.locator('text=Unread topics').first()).toBeVisible();
        }
    });

    test('/forums.php?action=search → /forum/search (legacy hop)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=search', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toBe('/forum/search');
    });

    test('/forums.php?action=search preserves the keywords query', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get(
            '/forums.php?action=search&keywords=hello+world',
            { maxRedirects: 0 },
        );
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toContain('/forum/search?');
        expect(location).toMatch(/keywords=hello(\+|%20|%2B)world/);
    });

    test('/forum/search renders the Livewire ForumSearch component', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.goto('/forum/search');
        expect(response).not.toBeNull();
        expect([200, 302]).toContain(response!.status());
        if (response!.status() === 200) {
            await expect(page.locator('text=Search forum posts').first()).toBeVisible();
        }
    });

    test('/forums.php?action=deletetopic&topicid=N → /forum/topic/N (legacy hop)', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get(
            '/forums.php?action=deletetopic&topicid=1',
            { maxRedirects: 0 },
        );
        expect(response.status()).toBe(302);
        const location = response.headers()['location'] ?? '';
        expect(location).toBe('/forum/topic/1');
    });

    test('/forums.php?action=deletetopic without topicid stays on legacy', async ({
        context,
        page,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.get('/forums.php?action=deletetopic', {
            maxRedirects: 0,
        });
        expect([200, 403, 404]).toContain(response.status());
    });
});
