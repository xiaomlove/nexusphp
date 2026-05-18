<?php

namespace App\Support;

/**
 * Stateless input validators extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `is_valid_id()`
 *   - `is_valid_user_class()`
 *   - `validip_format()`
 *   - `validemail()`
 *   - `validusername()`
 *   - `valid_file_name()`
 *   - `valid_class_name()`
 *
 * all collapse into the static methods below. The legacy functions
 * now proxy here so existing call sites (mostly `if (!validfoo(...))`
 * guards scattered across `public/*.php`) keep working unmodified.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Imdb} and {@see Ratio}.
 *
 * Every method's contract is pinned by a unit test in
 * `tests/Unit/Support/ValidatorsTest.php`, including the few legacy
 * quirks we deliberately preserve (e.g. `validClassName('')` returns
 * `true` because the legacy `strpos($_, $filename[0])` evaluation
 * with an empty subject hits the `strpos(_, '') === 0` short-circuit
 * — fixing that here would silently change validation for any caller
 * that depended on the buggy short-circuit).
 */
final class Validators
{
    /**
     * Lowest valid user-class value. Matches the legacy `UC_PEASANT`
     * constant defined in `include/core.php`. Pinned here so this
     * pure validator does not have to load the legacy bootstrap.
     */
    public const USER_CLASS_MIN = 0;

    /**
     * Highest valid user-class value. Matches the legacy
     * `UC_STAFFLEADER` constant defined in `include/core.php`. If a
     * new class tier is ever added to the legacy ladder, both this
     * constant and `include/core.php` need to grow together.
     */
    public const USER_CLASS_MAX = 16;

    /**
     * Validate that the input is a positive integer-like value
     * (`> 0` and equal to its own `floor()`). Accepts `mixed` because
     * legacy call sites pass `$_REQUEST` values, DB columns, and
     * occasionally pre-computed arithmetic like `is_valid_id($class + 1)`.
     */
    public static function isId(mixed $id): bool
    {
        return is_numeric($id) && ($id > 0) && (floor($id) == $id);
    }

    /**
     * Validate that the input is a known user-class value — a numeric,
     * integer-valued tier between `UC_PEASANT` (0) and
     * `UC_STAFFLEADER` (16) inclusive.
     *
     * Accepts `mixed` for the same reason `isId()` does — legacy
     * call sites pass `$_REQUEST` values and DB columns straight in.
     * Returns `false` for non-numeric input, fractional input, and
     * out-of-range integers. Matches the legacy
     * `is_valid_user_class()` body exactly:
     *   `is_numeric($class) && floor($class) == $class
     *    && $class >= UC_PEASANT && $class <= UC_STAFFLEADER`.
     */
    public static function isUserClass(mixed $class): bool
    {
        return is_numeric($class)
            && floor($class) == $class
            && $class >= self::USER_CLASS_MIN
            && $class <= self::USER_CLASS_MAX;
    }

    /**
     * Match an IPv4 dotted-quad pattern anywhere in the input
     * (legacy uses `preg_match` on a non-anchored pattern). Useful
     * for sniffing whether a free-text field "looks like" an IP —
     * NOT a strict IP-address parser. Mirrors the legacy contract
     * exactly: a string like `"prefix 1.2.3.4 suffix"` still
     * validates because the legacy regex has no `^...$` anchors.
     */
    public static function isIpv4Format(string $ip): bool
    {
        $ipPattern =
            '/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'.
            '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'.
            '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'.
            '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';

        return preg_match($ipPattern, $ip) === 1;
    }

    /**
     * Validate an e-mail via `FILTER_VALIDATE_EMAIL`. Identical
     * semantics to the legacy `validemail()` — the legacy function
     * literally is `filter_var(...) !== false`.
     */
    public static function isEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate a NexusPHP username: ASCII alphanumeric only, length
     * 3–20 inclusive. Empty string → false.
     *
     * Mirrors the legacy `validusername()` exactly, including the
     * subtle "length is the byte length, not the codepoint count"
     * (`strlen` not `mb_strlen`) — a multibyte input that happens to
     * use only allowed bytes after UTF-8 encoding still fails the
     * `allowedchars` check (no Cyrillic / CJK characters appear in
     * the allowlist), so the legacy behaviour is preserved.
     */
    public static function isUsername(string $username): bool
    {
        if ($username === '') {
            return false;
        }

        $allowedchars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = strlen($username);
        for ($i = 0; $i < $length; $i++) {
            if (strpos($allowedchars, $username[$i]) === false) {
                return false;
            }
        }

        if ($length < 3 || $length > 20) {
            return false;
        }

        return true;
    }

    /**
     * Validate a "safe" file name: lowercase ASCII alphanumeric plus
     * `_`, `.`, `/`. Empty string → true (mirrors the legacy quirk
     * where the for-loop simply never executes).
     *
     * Note the slash in the allowlist — legacy uses this for
     * category sub-paths like `audio/lossless/cssfile.css`, which
     * is why pure `basename`-style validation isn't enough.
     */
    public static function isFileName(string $filename): bool
    {
        $allowedchars = 'abcdefghijklmnopqrstuvwxyz0123456789_./';

        $total = strlen($filename);
        for ($i = 0; $i < $total; $i++) {
            if (strpos($allowedchars, $filename[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a "safe" CSS-class identifier: starts with a lowercase
     * letter, rest is lowercase letters / digits / `_`.
     *
     * Legacy quirk preserved: an empty input returns `true`, because
     * `strpos($allowedfirstchars, ''[0])` evaluates to
     * `strpos($allowedfirstchars, '') === 0` (in PHP 8+ `strpos`
     * with an empty needle returns `0`, not `false`). Every call
     * site already guards with `if ($class_name && !valid_class_name(...))`
     * so the empty-string case doesn't reach this validator in
     * practice; pinning the behaviour with a test prevents a future
     * "fix" from silently rejecting some other previously-OK input.
     */
    public static function isClassName(string $filename): bool
    {
        $allowedfirstchars = 'abcdefghijklmnopqrstuvwxyz';
        $allowedchars = 'abcdefghijklmnopqrstuvwxyz0123456789_';

        // Explicit `$filename === ''` branch so the empty-string case
        // doesn't trip PHP 8's "Undefined array key 0" warning on
        // `$filename[0]`. The end result still matches legacy: an
        // empty needle in `strpos` returns 0, which is not `=== false`,
        // so we fall through and return true.
        $firstChar = $filename === '' ? '' : $filename[0];
        if (strpos($allowedfirstchars, $firstChar) === false) {
            return false;
        }
        $total = strlen($filename);
        for ($i = 1; $i < $total; $i++) {
            if (strpos($allowedchars, $filename[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate an upload filename: reject control chars (`\0`..`\x1f`),
     * Windows path separators (`\`, `/`), and characters illegal on
     * NTFS (`:`, `?`, `*`, `#`, `<`, `>`, `|`) plus `\xff`. Mirrors
     * the legacy `validfilename()` exactly — used by the upload
     * pipeline before persisting a torrent's display name.
     *
     * Empty string → false (legacy `preg_match` on an unanchored
     * blocklist returns `0` for empty input because the regex
     * requires at least one matching byte). This differs from the
     * other "valid name" helpers here (`isFileName` / `isClassName`)
     * which accept empty.
     *
     * Note: this is a blocklist, not an allowlist — multibyte UTF-8
     * filenames are accepted (a legacy upload feature). Use
     * `isFileName()` instead when you need ASCII-only validation.
     */
    public static function isUploadFilename(string $name): bool
    {
        return preg_match('/^[^\0-\x1f:\\\\\/?*\xff#<>|]+$/si', $name) === 1;
    }
}
