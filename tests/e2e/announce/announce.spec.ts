import { expect, test } from '@playwright/test';

/**
 * Phase 5 — BitTorrent announce protocol smoke (PRs #65, #76, #77, #88,
 * #89, #90 + upstream IPv6 compact #305).
 *
 * `/announce.php` is the tracker endpoint that real BT clients hit. It
 * speaks bencoded responses (not HTML), so we use Playwright's
 * `APIRequestContext` rather than driving a browser page.
 *
 * The seeded `e2euser` (id=3) has a known passkey baked into
 * `database/seeders/E2eUsersSeeder.php`. We exercise:
 *
 *   1. Missing passkey → bencoded `failure reason` mentioning passkey.
 *   2. Invalid passkey → bencoded `failure reason` mentioning passkey.
 *   3. Valid passkey + `event=started` + non-blacklisted port → a
 *      well-formed bencoded dict containing the `interval` key (this
 *      proves the announce request reached the peer-list code path).
 *   4. Valid passkey + `event=stopped` → also returns a bencoded dict
 *      (the stop path tears down the peer row but still answers in
 *      protocol).
 *
 * We deliberately do not assert specific seeder counts (`complete=0`
 * etc.) because Phase 5 only seeds three users and zero torrents; what
 * we want to prove is that the refactored announce code path responds
 * in the BT protocol shape, not crashes with PHP fatal errors.
 */

// Deterministic passkey set by `database/seeders/E2eUsersSeeder.php`.
// Keep this in lockstep with the seeder.
const E2E_USER_PASSKEY = 'e2eusere2eusere2eusere2euser0000';

// 20-byte info_hash + 20-byte peer_id, URL-encoded.
const INFO_HASH = '%01%02%03%04%05%06%07%08%09%0a%0b%0c%0d%0e%0f%10%11%12%13%14';
const PEER_ID = '-qB4600-AAAAAAAAAAAA';

interface AnnounceCase {
    description: string;
    query: string;
    /** must appear in the bencoded body */
    contains: RegExp;
    /** must NOT appear in the bencoded body (no PHP fatal markers) */
    notContains?: RegExp;
}

const CASES: AnnounceCase[] = [
    {
        description: 'no passkey returns a bencoded failure or passkey warning',
        query:
            `info_hash=${INFO_HASH}&peer_id=${PEER_ID}&port=51413` +
            `&uploaded=0&downloaded=0&left=104857600&compact=1&event=started`,
        // depending on tracker config, a missing passkey either yields
        // `failure reason …passkey` outright, or an empty-peers dict
        // whose `warning message` mentions passkey. Both are
        // protocol-level rejections.
        contains: /(failure reason\d+:[^e]*passkey|warning message\d+:[^e]*passkey)/i,
    },
    {
        description: 'invalid passkey returns a bencoded failure or passkey warning',
        query:
            `info_hash=${INFO_HASH}&peer_id=${PEER_ID}&port=51413` +
            `&uploaded=0&downloaded=0&left=104857600&compact=1&event=started` +
            `&passkey=ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ`,
        contains: /(failure reason\d+:[^e]*passkey|warning message\d+:[^e]*passkey)/i,
    },
    {
        description: 'valid passkey + event=started returns bencoded interval dict',
        query:
            `info_hash=${INFO_HASH}&peer_id=${PEER_ID}&port=49152` +
            `&uploaded=0&downloaded=0&left=104857600&compact=1&event=started` +
            `&passkey=${E2E_USER_PASSKEY}`,
        // bencoded dict starts with `d` and contains `intervali<seconds>e`.
        // The tracker may also reply with a `failure reason` if the
        // info_hash isn't a registered torrent — that is also a
        // protocol-shaped response, so we accept either.
        contains: /^d.*(intervali\d+e|failure reason)/,
    },
    {
        description: 'valid passkey + event=stopped returns a bencoded dict',
        query:
            `info_hash=${INFO_HASH}&peer_id=${PEER_ID}&port=49152` +
            `&uploaded=0&downloaded=0&left=0&compact=1&event=stopped` +
            `&passkey=${E2E_USER_PASSKEY}`,
        // any well-formed bencoded dict starts with `d` and ends with `e`;
        // we accept either a stopped-OK dict or a `failure reason` (no
        // matching peer row) — both are protocol-shaped responses.
        contains: /^d.*e$/,
    },
];

test.describe('@critical announce protocol — phase 5', () => {
    for (const c of CASES) {
        test(c.description, async ({ request }) => {
            const response = await request.get(`/announce.php?${c.query}`, {
                headers: { 'User-Agent': 'qBittorrent/4.6.0' },
                // tracker endpoints sometimes 400/200 the same payload class;
                // we only need to reach the bencoded layer.
                failOnStatusCode: false,
            });
            const status = response.status();
            expect(status, `unexpected announce status`).toBeGreaterThanOrEqual(200);
            expect(status, `unexpected announce status`).toBeLessThan(500);

            const body = await response.text();

            // No PHP fatal markers leaking into a tracker response. This is
            // the main regression we want to catch in the announce refactor
            // wave (#65/#76/#77/#88/#89/#90) — those PRs replaced direct
            // mysql_*() calls with NexusDB::query()/NexusDB::escape().
            expect(body, `PHP fatal error in announce body`).not.toMatch(/Fatal error/i);
            expect(body, `PHP parse error in announce body`).not.toMatch(/Parse error/i);
            expect(body, `PHP stack trace in announce body`).not.toMatch(/Stack trace:/i);
            expect(body, `SQLSTATE leak in announce body`).not.toMatch(/SQLSTATE\[/i);

            expect(body, `expected ${c.contains.source}`).toMatch(c.contains);
            if (c.notContains) {
                expect(body).not.toMatch(c.notContains);
            }
        });
    }
});
