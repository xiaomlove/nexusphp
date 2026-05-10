import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';
import { smokeCheckPage } from '../helpers/smoke';

/**
 * Phase 5 — Filament admin smoke (`/nexusphp` SPA).
 *
 * The admin panel is a Filament v3 app mounted at `/nexusphp`. It
 * shares its session with the legacy NexusPHP front (one cookie jar,
 * one user). We rely on `loginAs(context, 'admin')` having already
 * provisioned the legacy `c_secure_pass` cookie via `/takelogin.php`;
 * Filament's panel auth bridges to that user record.
 *
 * Coverage:
 *   - Guest hits `/nexusphp` and lands on the Filament login form.
 *   - Guest hits `/nexusphp/login` and sees the rendered form.
 *   - Admin hits `/nexusphp` and sees the dashboard (`fi-page` body
 *     marker is the canonical Filament page wrapper).
 *   - Admin hits `/nexusphp/announce-monitor`, `/nexusphp/ip-search`,
 *     `/nexusphp/run-command`, `/nexusphp/plugin`,
 *     `/nexusphp/section/categories` — these are five Filament Pages
 *     and Resources that the admin SPA exposes; if any of them crashes
 *     the dashboard is unusable. Each must render a `fi-` Filament
 *     wrapper class somewhere in the body.
 */
test.describe('@admin Filament admin panel — phase 5', () => {
    test('guest /nexusphp bounces to a login page (Filament or legacy)', async ({ page }) => {
        // Filament's panel auth middleware redirects unauthenticated
        // requests through Laravel's `auth.login` route, which on this
        // install falls back to the legacy /login.php. Either landing
        // page proves the admin gate is enforced.
        const { html, response, consoleErrors } = await smokeCheckPage(page, '/nexusphp');
        expect(response.url(), 'guest /nexusphp should land on a login screen').toMatch(/(login\.php|nexusphp\/login)/);
        // page must NOT be the Filament dashboard.
        expect(html, 'guest must not see the Filament dashboard').not.toMatch(/<title>Dashboard/i);
        expect(consoleErrors).toEqual([]);
    });

    test('guest /nexusphp/login renders the Filament login form', async ({ page }) => {
        const { html, consoleErrors } = await smokeCheckPage(page, '/nexusphp/login');
        expect(html).toMatch(/fi-simple-page/);
        // form should expose Filament's email + password Livewire bindings
        expect(html, 'login form fields missing').toMatch(/wire:model="data\.email"/);
        expect(html, 'password field missing').toMatch(/wire:model="data\.password"/);
        expect(consoleErrors).toEqual([]);
    });

    interface AdminPage {
        description: string;
        url: string;
        contains: RegExp;
    }

    const ADMIN_PAGES: AdminPage[] = [
        {
            description: 'dashboard',
            url: '/nexusphp',
            contains: /fi-page/,
        },
        {
            description: 'announce-monitor page',
            url: '/nexusphp/announce-monitor',
            contains: /fi-page/,
        },
        {
            description: 'ip-search page',
            url: '/nexusphp/ip-search',
            contains: /fi-page/,
        },
        {
            description: 'run-command page',
            url: '/nexusphp/run-command',
            contains: /fi-page/,
        },
        {
            description: 'plugin cluster',
            url: '/nexusphp/plugin',
            contains: /fi-page/,
        },
        {
            description: 'section categories resource',
            url: '/nexusphp/section/categories',
            contains: /fi-page/,
        },
    ];

    for (const p of ADMIN_PAGES) {
        test(`admin ${p.url} (${p.description}) renders a Filament page`, async ({ page, context }) => {
            await loginAs(context, 'admin');
            const { html, consoleErrors } = await smokeCheckPage(page, p.url);
            expect(html, `expected ${p.contains.source} on ${p.url}`).toMatch(p.contains);
            expect(consoleErrors, `unexpected JS errors on ${p.url}`).toEqual([]);
        });
    }
});
