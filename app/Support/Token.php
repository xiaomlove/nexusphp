<?php

namespace App\Support;

/**
 * Stateless secret-token generators extracted from
 * `include/functions.php` (Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`").
 *
 * The legacy procedural helper
 *
 *   - `mksecret($len = 20)` (hex-encoded random bytes)
 *
 * collapses into the static method below. The legacy function now
 * proxies here so existing call sites (`AuthenticateController`,
 * `UserRepository`, `public/recover.php`, `public/usercp.php`, …)
 * keep working unmodified.
 *
 * Lives under `App\Support` (not `App\Services`) because the method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Strings}, {@see Validators}, {@see Network}.
 *
 * Every method's contract is pinned by a unit test in
 * `tests/Unit/Support/TokenTest.php`.
 */
final class Token
{
    /**
     * Return a hex-encoded string of `$bytes` cryptographically secure
     * random bytes — i.e. exactly `$bytes * 2` characters drawn from
     * the alphabet `[0-9a-f]`.
     *
     * Matches the legacy `mksecret()` body exactly:
     * `bin2hex(random_bytes($len))`. The default of 20 bytes (→ 40
     * hex chars) is also preserved verbatim so existing call sites
     * that omit the argument keep getting the same shape of token.
     *
     * Crypto note: `random_bytes()` is the CSPRNG-backed source —
     * **not** `rand()` / `mt_rand()`. Tokens generated here are safe
     * to use as auth secrets, password-reset nonces, IRC challenge
     * strings, etc.
     */
    public static function randomHex(int $bytes = 20): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
