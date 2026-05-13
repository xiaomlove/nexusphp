import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Phase 4 smoke coverage: the remaining legacy `*.php` front-controller
 * scripts that were rewritten in the mysql_*-→-NexusDB and
 * sqlesc()-→-NexusDB::escape() refactor wave (PRs #21..#41 + assorted
 * follow-ups). Each test logs in as the seeded `e2eadmin` user, visits
 * one URL, and asserts:
 *   - HTTP 2xx/3xx
 *   - no fatal-error markers (Fatal error, Parse error, Stack trace, ...)
 *     in the rendered body (handled by `smokeCheckPage`)
 *   - no non-noise JS console errors
 *   - a page-specific marker (title or heading) is present, so we know the
 *     legacy layout actually booted instead of returning a blank/200 stub.
 *
 * Notes:
 *   - `/bookmark.php` is an XML toggle endpoint (no UI), and `/invite.php`
 *     renders a minimal "Sorry / Permission Denied" template that
 *     legitimately lacks jQuery (a pre-existing template quirk unrelated
 *     to the PR #25/#28 refactor). Both are excluded from this smoke
 *     parameter list — the bookmark refactor (PR #25) is covered
 *     indirectly by `/torrents.php?bookmarks=1` which renders the user's
 *     bookmarks tab, and the invite refactor (PR #28) is covered by the
 *     existing PHPUnit Feature suite.
 *   - `/report.php` and `/polloverview.php` and `/userhistory.php?id=1`
 *     render the standard error template when no `id`/`type` matches
 *     real data; the smoke check still exercises the bootstrap + DB-init
 *     paths that the refactor wave touched.
 *   - `/polls.php` does not exist in this NexusPHP install (the codebase
 *     uses `/makepoll.php` + `/polloverview.php` instead), so it is not
 *     covered here.
 */
interface LegacyPageCase {
    description: string;
    url: string;
    /** marker that must appear in the rendered HTML */
    contains: RegExp;
}

const PAGES: LegacyPageCase[] = [
    {
        // /forums.php now 302→/forum by default (Strangler Fig flip).
        // ?legacy=1 keeps the legacy view reachable as the rollback
        // canary — that's the surface this smoke covers.
        description: 'forums.php?legacy=1 (PR #22, #41 forums refactor; canary post-flip)',
        url: '/forums.php?legacy=1',
        contains: /NexusPHP\s*::\s*Forums/i,
    },
    {
        description: 'torrents.php?bookmarks=1 (PR #25 bookmark list view)',
        url: '/torrents.php?bookmarks=1',
        contains: /NexusPHP\s*::\s*Torrents/i,
    },
    {
        description: 'news.php (PR #26 news refactor)',
        url: '/news.php',
        contains: /NexusPHP\s*::\s*Site News/i,
    },
    {
        description: 'rules.php (PR #26 rules refactor)',
        url: '/rules.php',
        contains: /NexusPHP\s*::\s*Rules/i,
    },
    {
        description: 'faq.php (PR #26 faq refactor)',
        url: '/faq.php',
        contains: /NexusPHP\s*::\s*FAQ/i,
    },
    {
        description: 'mybonus.php (PR #27 karma bonus refactor)',
        url: '/mybonus.php',
        contains: /Karma Bonus/i,
    },
    {
        description: 'topten.php (PR #24 top10 refactor)',
        url: '/topten.php',
        contains: /NexusPHP\s*::\s*Top 10/i,
    },
    {
        description: 'makepoll.php (PR #33 polls refactor)',
        url: '/makepoll.php',
        contains: /Make poll/i,
    },
    {
        description: 'polloverview.php (PR #33 polls refactor, error template)',
        url: '/polloverview.php',
        contains: /Powered by NexusPHP/i,
    },
    {
        description: 'fun.php (PR #34 fun page)',
        url: '/fun.php',
        contains: /<title>Fun<\/title>/i,
    },
    {
        description: 'takeedit.php (PR #35 takeedit error path)',
        url: '/takeedit.php',
        contains: /Edit failed/i,
    },
    {
        description: 'report.php (PR #35 report error path)',
        url: '/report.php',
        contains: /Powered by NexusPHP/i,
    },
    {
        description: 'staffpanel.php (PR #21, #39 admin panel)',
        url: '/staffpanel.php',
        contains: /Administration/i,
    },
    {
        description: 'catmanage.php (PR #37 category manager)',
        url: '/catmanage.php',
        contains: /Category Management/i,
    },
    {
        description: 'modrules.php (rules management)',
        url: '/modrules.php',
        contains: /Rules Manangement/i,
    },
    {
        description: 'log.php (PR #36 daily log)',
        url: '/log.php',
        contains: /NexusPHP\s*::\s*Daily Log/i,
    },
    {
        description: 'viewrequests.php (PR #23 requests list)',
        url: '/viewrequests.php',
        contains: /NexusPHP\s*::\s*Requests/i,
    },
    {
        description: 'staff.php (staff list page)',
        url: '/staff.php',
        contains: /NexusPHP\s*::\s*Staff/i,
    },
    {
        description: 'contactstaff.php (contact staff form)',
        url: '/contactstaff.php',
        contains: /Send message to Staff/i,
    },
    {
        description: 'upload.php (torrent upload form)',
        url: '/upload.php',
        contains: /NexusPHP\s*::\s*Upload/i,
    },
    {
        description: 'userhistory.php?id=1 (history error path)',
        url: '/userhistory.php?id=1',
        contains: /History Error|Powered by NexusPHP/i,
    },
];

test.describe('@smoke legacy pages — phase 4 (authenticated as admin)', () => {
    for (const c of PAGES) {
        test(`${c.description} renders without fatal errors`, async ({ page, context }) => {
            await loginAs(context, 'admin');
            const { html, consoleErrors } = await smokeCheckPage(page, c.url);
            expect(html, `expected ${c.contains.source} in ${c.url}`).toMatch(c.contains);
            expect(consoleErrors, `unexpected JS errors on ${c.url}`).toEqual([]);
        });
    }
});
