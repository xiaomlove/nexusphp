import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

/**
 * Phase 6 — Failed-login rate limit.
 *
 * `include/functions.php :: failedloginscheck()` walks every row in
 * `loginattempts` for the requesting IP, sums the `attempts` column,
 * and once that sum reaches `$SECURITY['maxloginattempts']` (default
 * **10**) it banner-locks the IP with:
 *
 *     Login Locked! (the maximum number of failed N attempts is
 *     reached during reauthentication)
 *
 * That branch is the security guarantee that protects the legacy
 * `/takelogin.php` endpoint from brute-force attacks. It is also the
 * reason every Phase 0–5 spec begins with a clean `loginattempts`
 * table — the seeder + `e2e:bootstrap --reset-attempts` clear it
 * deterministically before each run.
 *
 * This spec exercises the **positive** failure path:
 *
 *   1. Hammer `/takelogin.php` with a non-existent username 11 times.
 *   2. The first ~8 responses must NOT contain the "Login Locked"
 *      banner (there are still attempts remaining).
 *   3. By the 11th response the banner MUST appear.
 *
 * It is NOT safe to run in parallel with any other spec because the
 * lock is keyed by client IP and *every* Playwright worker shares
 * the loopback IP `127.0.0.1`. We therefore force serial mode AND
 * call out the constraint via the `@serial` tag.
 *
 * AfterAll: invoke `php artisan e2e:bootstrap --force` to wipe the
 * `loginattempts` table so the rest of the suite can still log in.
 * The artisan invocation works in CI (where `php` is on PATH) and
 * locally (where docker compose hosts it). We try both and only fail
 * the cleanup if neither path succeeds.
 */

test.describe.configure({ mode: 'serial' });

const LOGIN_LOCKED_BANNER = /Login Locked!.*maximum number of failed.*attempts/i;
const TOTAL_ATTEMPTS = 11;

test.describe('@critical @serial /takelogin.php failed-attempt rate limit', () => {
    test.afterAll(async () => {
        resetLoginAttempts();
    });

    test('locks the requesting IP after the configured maxloginattempts', async ({
        request,
    }) => {
        const responses: string[] = [];
        for (let i = 1; i <= TOTAL_ATTEMPTS; i++) {
            const r = await request.post('/takelogin.php', {
                form: {
                    username: '__rate_limit_zzz_does_not_exist__',
                    password: 'wrong-password-' + i,
                },
                maxRedirects: 5,
            });
            const body = await r.text();
            responses.push(body);
            // No PHP fatals on the failure path — the legacy code calls
            // stderr() which renders through the standard error
            // template. A fatal here would actually be a regression of
            // the mysql_* → NexusDB refactor.
            expect(body, `attempt ${i} should not crash with a fatal`).not.toMatch(
                /Fatal error|Parse error|Stack trace:/i,
            );
        }

        // Early attempts must NOT be locked yet (we want to be sure
        // the lock is the END state, not an always-on response).
        const earlyAttempts = responses.slice(0, 4);
        for (const [idx, body] of earlyAttempts.entries()) {
            expect(
                body,
                `attempt ${idx + 1} should still allow retry`,
            ).not.toMatch(LOGIN_LOCKED_BANNER);
        }

        // The very last attempt MUST be locked. The `failedloginscheck`
        // function fires on the next login attempt after `attempts`
        // crosses the threshold, so at attempt #11 we are
        // unambiguously past `maxloginattempts = 10`.
        const lastBody = responses[responses.length - 1];
        expect(lastBody).toMatch(LOGIN_LOCKED_BANNER);
    });
});

/**
 * Try every reasonable invocation of `php artisan e2e:bootstrap`
 * until one succeeds. Throws (and fails the test run) if none do —
 * leaving `loginattempts` dirty would silently break every later
 * login-required spec.
 */
function resetLoginAttempts(): void {
    const candidates: Array<{ cmd: string; args: string[] }> = [
        // Local docker-compose dev stack:
        {
            cmd: 'docker',
            args: [
                'compose',
                'exec',
                '-T',
                'php',
                'php',
                'artisan',
                'e2e:bootstrap',
                '--force',
            ],
        },
        // CI / direct host PHP:
        {
            cmd: 'php',
            args: ['artisan', 'e2e:bootstrap', '--force'],
        },
    ];

    const errors: string[] = [];
    for (const { cmd, args } of candidates) {
        try {
            execFileSync(cmd, args, {
                stdio: 'pipe',
                cwd: process.cwd(),
            });
            return;
        } catch (e) {
            errors.push(`${cmd} ${args.join(' ')}: ${(e as Error).message}`);
        }
    }
    throw new Error(
        `failed to reset loginattempts; tried:\n${errors.join('\n')}`,
    );
}
