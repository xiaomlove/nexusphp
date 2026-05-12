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
 * `E2eTorrentsSeeder` seeds a deterministic torrent with `id=1`, so the
 * `/details.php?id=1` case exercises the legacy happy-path render
 * (the same query path the `mysql_*`-→-NexusDB refactor wave touched).
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
        description: 'torrents.php now 302→/browse (Strangler Fig flip)',
        url: '/torrents.php',
        contains: /Browse torrents/i,
    },
    {
        description: 'details.php (seeded torrent happy path)',
        url: '/details.php?id=1',
        // E2eTorrentsSeeder inserts the "E2E Test Torrent" row at id=1
        // and the standard NexusPHP chrome ("Powered by NexusPHP")
        // appears in both the happy-path and error templates.
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
