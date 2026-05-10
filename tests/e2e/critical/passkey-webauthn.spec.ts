import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/api-login';

/**
 * Phase 6 — WebAuthn passkey enrollment (#405 / #427).
 *
 * NexusPHP ships two unrelated things called "passkey":
 *
 *   1. The legacy 32-character **tracker passkey** baked into every
 *      `User` row — a shared secret BT clients append to
 *      `/announce.php?passkey=…`. That's what Phase 5's
 *      `announce.spec.ts` already covers.
 *
 *   2. The **WebAuthn / FIDO2 passkey** (#405 + #427), where the
 *      `Passkey` Eloquent model maps to `user_passkeys` and stores
 *      `(aaguid, credential_id, public_key, counter)` per registered
 *      authenticator. Enrollment goes through:
 *
 *        - Browser:  `Passkey.createRegistration()` in
 *                    `public/js/passkey.js`
 *        - PHP:      `UserPasskeyRepository::getCreateArgs(...)` and
 *                    `UserPasskeyRepository::processCreate(...)`
 *        - Wire:     `POST /ajax.php` with
 *                    `action=getPasskeyCreateArgs`
 *                    and
 *                    `action=processPasskeyCreate`
 *
 * A full enrollment round-trip needs a virtual authenticator wired
 * over CDP (`browserContext.addInitScript` + `webauthn.addCredential`),
 * which is reasonable but heavy. For Phase 6 we settle for the two
 * cheaper assertions that catch the bulk of regressions:
 *
 *   A. The user-control-panel security tab must render the
 *      `id="passkey_create"` enrollment button (proves the
 *      `UserPasskeyRepository::renderList()` server-side render path
 *      is wired into `usercp.php`).
 *
 *   B. Authenticated `POST /ajax.php` with
 *      `action=getPasskeyCreateArgs` must return a valid JSON
 *      envelope `{ ret: 0, data: { challengeId, options.publicKey.rp,
 *      options.publicKey.user } }` (proves the WebAuthn challenge
 *      generator runs without PHP fatals and the route is
 *      properly auth-gated).
 *
 *   C. The same call with NO session cookies must NOT leak
 *      `challengeId` — guest access is denied.
 */

test.describe('@critical WebAuthn passkey enrollment (#405 / #427)', () => {
    test('usercp settings tab renders the enrollment button', async ({
        page,
        context,
    }) => {
        await loginAs(context, 'user');

        // The "security" tab of usercp.php is where
        // UserPasskeyRepository::renderList() prints the passkey
        // enrollment button. The legacy switch in
        // public/usercp.php :: case 'security' routes to it.
        const response = await page.request.get('/usercp.php?action=security');
        expect(response.status()).toBeLessThan(400);
        const html = await response.text();

        expect(html).not.toMatch(/Fatal error|Parse error/i);
        expect(html).toContain('id="passkey_create"');
        // The button is rendered by UserPasskeyRepository::renderList,
        // which also wires the click handler that calls
        // `Passkey.createRegistration()` from /js/passkey.js.
        expect(html).toMatch(/Passkey\.createRegistration\s*\(/);
    });

    test('admin POST /ajax.php?action=getPasskeyCreateArgs returns a valid challenge', async ({
        page,
        context,
    }) => {
        await loginAs(context, 'admin');

        const response = await page.request.post('/ajax.php', {
            form: { action: 'getPasskeyCreateArgs' },
        });
        expect(response.status()).toBe(200);

        const text = await response.text();
        expect(text).not.toMatch(/Fatal error|Parse error/i);

        const payload = JSON.parse(text);
        expect(payload.ret).toBe(0);
        expect(payload.data).toBeDefined();
        expect(typeof payload.data.challengeId).toBe('string');
        expect(payload.data.challengeId.length).toBeGreaterThanOrEqual(32);

        const opts = payload.data.options?.publicKey;
        expect(opts).toBeDefined();
        // The relying-party id is the host serving the request.
        expect(typeof opts.rp.id).toBe('string');
        expect(typeof opts.rp.name).toBe('string');
        // The user record carries the seeded admin's username.
        expect(opts.user.name).toBe('e2eadmin');
        expect(opts.user.displayName).toBe('e2eadmin');
        // We must advertise at least one supported credential alg
        // (the upstream config registers ES256 / EdDSA / RS256).
        expect(Array.isArray(opts.pubKeyCredParams)).toBe(true);
        expect(opts.pubKeyCredParams.length).toBeGreaterThan(0);
    });

    test('guest POST /ajax.php?action=getPasskeyCreateArgs is rejected', async ({
        request,
    }) => {
        // Use the `request` fixture (no session cookies) so this acts
        // as an unauthenticated visitor.
        const response = await request.post('/ajax.php', {
            form: { action: 'getPasskeyCreateArgs' },
        });
        // We do not assert a specific status code (the legacy
        // ajax.php returns 200 with a printed error in some paths).
        // What matters is that no challenge envelope is produced.
        const text = await response.text();
        expect(text).not.toContain('"challengeId"');
        expect(text).not.toMatch(/"ret"\s*:\s*0/);
    });
});
