import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Smoke coverage for the seven user-visible pages most affected by the
 * mysql_*-→-NexusDB and sqlesc()-→-NexusDB::escape() refactor wave (Phase 2).
 *
 * Each test logs in as `e2eadmin` (created by `php artisan e2e:bootstrap`),
 * visits one URL, and asserts:
 *   - HTTP 2xx/3xx
 *   - no fatal-error markers (Fatal error, Parse error, Stack trace, etc.)
 *     in the rendered body
 *   - no non-noise JS console errors
 *   - a page-specific substring (title or heading) is present
 *
 * The "details torrent" case intentionally uses id=1 even though no torrent
 * is seeded; legacy NexusPHP renders "Error: No torrent with this ID" through
 * its standard layout, which is still a green-path render of the error
 * branch and exercises the same NexusDB code paths the refactor touches.
 */
interface LegacyPageCase {
    description: string;
    url: string;
    /** marker that must appear in the rendered HTML */
    contains: RegExp;
}

const PAGES: LegacyPageCase[] = [
    {
        description: 'index.php (legacy home)',
        url: '/index.php',
        contains: /NexusPHP\s*::\s*Home/i,
    },
    {
        description: 'torrents.php (legacy torrent list)',
        url: '/torrents.php',
        contains: /NexusPHP\s*::\s*Torrents/i,
    },
    {
        description: 'details.php (no-torrent error path)',
        url: '/details.php?id=1',
        // legacy error template renders "Error" inside <h2>; we want at least
        // the standard chrome ("Powered by NexusPHP") so we know the layout
        // booted instead of crashing midway through.
        contains: /Powered by NexusPHP/i,
    },
    {
        description: '/browse (Livewire TorrentBrowse #79)',
        url: '/browse',
        // Livewire-specific markers: the page mounts a wire:id'd root and an
        // <h1>Browse torrents</h1>. If Livewire fails to render the component
        // these will be missing.
        contains: /wire:id=|Browse torrents/i,
    },
    {
        description: 'usercp.php (control panel)',
        url: '/usercp.php',
        contains: /Control Panel/i,
    },
    {
        description: 'userdetails.php?id=1 (admin profile)',
        url: '/userdetails.php?id=1',
        contains: /e2eadmin/i,
    },
    {
        description: 'messages.php (inbox)',
        url: '/messages.php',
        contains: /Inbox/i,
    },
];

test.describe('@smoke legacy pages (authenticated as admin)', () => {
    for (const c of PAGES) {
        test(`${c.description} renders without fatal errors`, async ({ page, context }) => {
            await loginAs(context, 'admin');
            const { html, consoleErrors } = await smokeCheckPage(page, c.url);
            expect(html, `expected ${c.contains.source} in ${c.url}`).toMatch(c.contains);
            expect(consoleErrors, `unexpected JS errors on ${c.url}`).toEqual([]);
        });
    }
});
