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
 *     indirectly by `/torrents.php?legacy=1&inclbookmarked=1` which
 *     renders the user's bookmarks tab on the legacy canary (the modern
 *     `/browse?inclbookmarked=only` equivalent is covered separately by
 *     the Livewire `TorrentBrowse` tests), and the invite refactor
 *     (PR #28) is covered by the existing PHPUnit Feature suite.
 *   - `/report.php` renders the standard error template when no
 *     `id`/`type` matches real data; the smoke check still exercises
 *     the bootstrap + DB-init paths that the refactor wave touched.
 *   - `/polloverview.php` and `/userhistory.php` were both migrated
 *     to Laravel controllers (`PollOverviewController` Phase 2,
 *     `UserHistoryController` Phase 3). The legacy `stdfoot()`
 *     footer (and therefore the "Powered by NexusPHP" marker) is no
 *     longer rendered for those routes; the smoke check now asserts
 *     the chrome-less envelope's `<title>...</title>` marker instead.
 *     Same trade-off the `DonorlistController` / `UserBanLogController`
 *     migrations made.
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
        // /torrents.php?bookmarks=1 now 302→/browse?inclbookmarked=only
        // (Strangler Fig flip). The legacy bookmark tab is reachable
        // via the rollback canary URL — that's the surface this
        // smoke covers (legacy bookmark filter param is
        // ?inclbookmarked=1, not ?bookmarks=1, which was always just
        // an escape-hatch flag).
        description: 'torrents.php?legacy=1&inclbookmarked=1 (PR #25 bookmark list view; canary post-flip)',
        url: '/torrents.php?legacy=1&inclbookmarked=1',
        contains: /NexusPHP\s*::\s*Torrents/i,
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
        // Phase 2 migration: PollOverviewController renders a
        // chrome-less envelope (no legacy `stdfoot()`); marker
        // updated from "Powered by NexusPHP" to the controller's
        // `<title>` so the smoke probe still asserts the new
        // contract booted instead of returning a blank 200.
        description: 'polloverview.php (PR #33 polls refactor; Phase 2 migration)',
        url: '/polloverview.php',
        contains: /<title>Polls Overview<\/title>/i,
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
        // Phase 3 migration: UserHistoryController renders a chrome-less
        // envelope (no legacy `stdfoot()`). e2eadmin is seeded with id=1
        // by E2eUsersSeeder, so `?id=1&action=viewposts` exercises the
        // own-posts happy path (zero posts → empty table rendered).
        description: 'userhistory.php?id=1&action=viewposts (Phase 3 migration)',
        url: '/userhistory.php?id=1&action=viewposts',
        contains: /<title>Posts history/i,
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
