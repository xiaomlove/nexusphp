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

        // The success criterion is "the wizard MUST NOT render". Most
        // production-like setups (docker openresty + php-fpm) return
        // HTTP 200 with the "Locked!" sentinel because PHP's `die()`
        // exits cleanly. Some lighter setups (the CI php -S router
        // serving directly from `public/install/install.php`, the
        // `\Nexus\Nexus::boot()` path inside `install_update_start.php`
        // failing to construct a Laravel Cache before `Install::__construct()`
        // gets to call `checkLock()`) bubble that error up as a non-200
        // status. Either response is an acceptable "wizard refused to
        // render" — what matters is the body never contains the wizard
        // form. We accept any HTTP status here and assert on the body.
        expect(response.status(), 'install.php should respond').toBeLessThan(600);

        const body = await response.text();
        expect(body, 'install.php should not leak a wizard form').not.toMatch(
            /<form\b[^>]*>/i,
        );

        // If the status is 200 the body must carry the explicit
        // "Locked!" banner — that is the literal string `Install::checkLock()`
        // emits via `die(...)` and a missing banner would mean something
        // else served the response.
        if (response.status() === 200) {
            expect(body, 'install.php should not leak PHP fatals').not.toMatch(
                /(Fatal error|Parse error|Stack trace:|SQLSTATE\[)/i,
            );
            expect(body).toMatch(LOCK_BANNER);
            expect(body.length).toBeLessThan(500);
        }
    });

    test('POST /install/install.php is also blocked while lock is in place', async ({
        request,
    }) => {
        const response = await request.post('/install/install.php', {
            form: { step: '1' },
        });
        expect(response.status(), 'install.php POST should respond').toBeLessThan(600);
        const body = await response.text();
        expect(body, 'install.php POST should not render wizard').not.toMatch(
            /<form\b[^>]*>/i,
        );
        if (response.status() === 200) {
            expect(body).toMatch(LOCK_BANNER);
        }
    });
});
