import { expect, test } from '@playwright/test';

/**
 * Phase 6 — Install bootstrap (#74) negative path.
 *
 * `/install/install.php` is the disposable web installer that
 * walks an operator through the 5-step wizard (requirements check →
 * .env config → DB migration → admin creation → finalisation) on a
 * fresh deployment. After the last step it touches
 * `dont_delete_install.lock` in the repo root, and on every later
 * request it short-circuits with the literal string:
 *
 *     Locked! Delete .lock file first
 *
 * That sentinel is the safeguard that prevents an attacker from
 * re-installing the tracker over a live database. Phase 0's
 * `e2e:bootstrap` always touches that file, so for our test stack
 * the installer must always be locked.
 *
 * We deliberately do NOT exercise the wizard's positive path here —
 * doing so would wipe and re-seed the database in the middle of the
 * Playwright run, breaking every other spec that depends on the
 * seeded users / settings. The positive path is covered by
 * `php artisan e2e:bootstrap` itself (which the CI workflow runs
 * before this spec).
 */

const LOCK_BANNER = /Locked!\s+Delete\s+\.lock\s+file\s+first/i;

test.describe('@critical install bootstrap (#74)', () => {
    test('install.php short-circuits with "Locked!" banner when dont_delete_install.lock exists', async ({
        request,
    }) => {
        const response = await request.get('/install/install.php');
        expect(response.status()).toBe(200);

        const body = await response.text();
        expect(body, 'install.php should not leak PHP fatals').not.toMatch(
            /(Fatal error|Parse error|Stack trace:|SQLSTATE\[)/i,
        );
        expect(body).toMatch(LOCK_BANNER);

        // The lock-file branch must not also render the wizard form
        // (i.e. the short-circuit must happen BEFORE step rendering).
        // Step pages emit a `<form` element; the lock message is bare.
        expect(body.toLowerCase()).not.toContain('<form');
        expect(body.length).toBeLessThan(500);
    });

    test('POST /install/install.php is also blocked while lock is in place', async ({
        request,
    }) => {
        const response = await request.post('/install/install.php', {
            form: { step: '1' },
        });
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).toMatch(LOCK_BANNER);
        expect(body.toLowerCase()).not.toContain('<form');
    });
});
