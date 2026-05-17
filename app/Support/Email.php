<?php

namespace App\Support;

/**
 * Stateless email helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `safe_email()`           (strip a small set of metacharacters
 *                              that would otherwise let an address
 *                              smuggle MIME header injection or
 *                              shell-escape sequences into mail /
 *                              SQL transports)
 *   - `get_email_encode()`     (pick the legacy charset for the
 *                              outgoing email body based on the
 *                              language folder cookie — gbk for
 *                              `chs`/`cht`, utf-8 otherwise)
 *   - `change_email_encode()`  (`iconv` from utf-8 to the legacy
 *                              charset, ignoring un-mappable chars)
 *
 * collapse into the static methods below. The fourth member of the
 * cluster, `check_email()`, has TWO responsibilities: a pure regex
 * check AND a `bannedemails` DB lookup. The regex half moves here
 * as {@see Email::isWellFormed()}; the DB half stays in the legacy
 * proxy so this class remains free of DI, DB, and configuration —
 * matching the convention used by every other class under
 * `App\Support` ({@see Imdb}, {@see Ratio}, {@see Validators},
 * {@see Format}, {@see Strings}, {@see Time}, {@see Codec},
 * {@see BBCode}, {@see Cache}).
 *
 * The contract is pinned by `tests/Unit/Support/EmailTest.php`,
 * including the legacy quirks deliberately preserved:
 *
 *   - `sanitizeForDisplay()` strips the four legacy-escape strings
 *     (`\'`, `\"`, `\\`) literally — these were originally
 *     `magic_quotes_gpc` artefacts on PHP < 5.4. They no longer
 *     occur in real input, but the strip is preserved so a
 *     hand-crafted POST with a literal backslash sequence cannot
 *     suddenly land in a transport that no longer protects against
 *     it.
 *   - `charsetFor()` only special-cases the two Chinese language
 *     folders (`chs`, `cht`) and falls back to utf-8 for every
 *     other input — including the empty string, an unknown locale,
 *     or a folder name with mixed case. The legacy used a strict
 *     `==` equality check, so `CHS`/`Cht`/etc. would already have
 *     fallen back to utf-8.
 *   - `isWellFormed()` uses the legacy regex verbatim, NOT
 *     `filter_var(..., FILTER_VALIDATE_EMAIL)`. The two are not
 *     equivalent — the legacy regex is *stricter* than RFC 5322
 *     (e.g. rejects leading `+`/`-`, rejects single-label
 *     domains, rejects quoted-local-part). Existing seed data
 *     and admin tooling assume the stricter rule.
 */
final class Email
{
    /**
     * The legacy "Chinese" language folders that map to GBK
     * encoding. Every other input falls back to UTF-8.
     */
    private const CHINESE_LANG_FOLDERS = ['chs', 'cht'];

    /**
     * The legacy `check_email()` regex. Stricter than RFC 5322 /
     * `FILTER_VALIDATE_EMAIL`:
     *
     *   - local part must start with an ASCII letter or digit
     *     (no leading `+`, `-`, `_`, `.`)
     *   - local part body is `[A-Za-z0-9_.+\-]*`
     *   - domain must have at least one dot (no single-label
     *     domains like `user@localhost`)
     *   - every domain label must start with an ASCII letter or
     *     digit
     *
     * Pinned by test; do not relax — every public flow that
     * touches email (signup, invite, recover, donate, mailtest,
     * email-gateway, linksmanage) calls this regex via the legacy
     * `check_email()` proxy.
     */
    private const WELL_FORMED_PATTERN =
        '/^[A-Za-z0-9][A-Za-z0-9_.+\-]*@[A-Za-z0-9][A-Za-z0-9_+\-]*(\.[A-Za-z0-9][A-Za-z0-9_+\-]*)+$/';

    /**
     * Strip the small set of metacharacters the legacy
     * `safe_email()` removed from an address before handing it to
     * mail / SQL transports. Returns the cleaned address as a
     * plain string; does NOT validate.
     *
     * The strip set is (in legacy order): `<`, `>`, `\'`, `\"`,
     * `\\`. Note the literal backslash-quote / backslash-backslash
     * sequences — these are `magic_quotes_gpc` artefacts the
     * legacy left in place defensively. Modern PHP cannot generate
     * them automatically, but a hand-crafted POST can, and the
     * legacy contract still removes them.
     */
    public static function sanitizeForDisplay(string $email): string
    {
        return str_replace(
            ['<', '>', "\\'", '\\"', '\\\\'],
            '',
            $email,
        );
    }

    /**
     * Pick the legacy charset string for the outgoing email body
     * based on the language-folder cookie value. Returns `"gbk"`
     * for `chs`/`cht`, `"utf-8"` for every other input (including
     * the empty string and unknown locales).
     *
     * Used by the legacy `change_email_encode()` to drive `iconv`,
     * and by call sites that build their own `Content-Type`
     * headers.
     */
    public static function charsetFor(string $langFolder): string
    {
        return in_array($langFolder, self::CHINESE_LANG_FOLDERS, true)
            ? 'gbk'
            : 'utf-8';
    }

    /**
     * Convert `$content` from UTF-8 to the legacy charset for
     * `$langFolder`, using `iconv` with the `//IGNORE` modifier so
     * un-mappable codepoints are dropped silently rather than
     * aborting the conversion (mirrors the legacy
     * `change_email_encode()` contract).
     *
     * Returns the converted string, or `false` if `iconv` itself
     * fails (the legacy behaviour — `iconv` returns `false` and the
     * caller's `mail()` body becomes the literal `false`. We
     * preserve that shape so call sites that already null-check
     * the result keep working unchanged).
     */
    public static function convertCharset(string $langFolder, string $content): string|false
    {
        return iconv('utf-8', self::charsetFor($langFolder).'//IGNORE', $content);
    }

    /**
     * Return true if `$email` matches the legacy `check_email()`
     * regex (see {@see WELL_FORMED_PATTERN}). Does NOT consult the
     * `bannedemails` table — that lookup stays in the legacy
     * `check_email()` proxy so this class remains DB-free.
     *
     * Empty string → false. Anything that fails the regex →
     * false. The regex is stricter than RFC 5322 by design.
     */
    public static function isWellFormed(string $email): bool
    {
        return preg_match(self::WELL_FORMED_PATTERN, $email) === 1;
    }

    /**
     * Regex matcher used by legacy `EmailBanned()` / `EmailAllowed()`.
     * `@host` entries become subdomain-accepting regexes (`@` rewritten
     * to `[@\.]`); naked entries match by exact equality.
     *
     * Two legacy branches are unreachable and intentionally preserved:
     * entries containing `@` but not starting with one (e.g. `user@host`),
     * and entries with a trailing `@` (e.g. `user@`) — both are pinned.
     */
    public static function matchesRegexList(string $email, string $listValue): bool
    {
        $needle = trim(strtolower($email));
        $normalised = preg_replace('/[[:space:]]+/', ' ', trim($listValue));
        if ($normalised === null || $normalised === '') {
            return false;
        }
        foreach (explode(' ', $normalised) as $entry) {
            $entry = trim(strtolower((string) preg_replace('/\./', '\\.', $entry)));

            if (strstr($entry, '@')) {
                if (preg_match('/^@/', $entry)) {
                    $rewritten = preg_replace('/^@/', '[@\\.]', $entry);
                    if (preg_match('/'.$rewritten.'$/', $needle)) {
                        return true;
                    }
                }
                // Legacy quirk: "user@host" and "user@" entries are unreachable.
            } elseif (preg_match('/@$/', $entry)) {
                if (preg_match('/^'.$entry.'/', $needle)) {
                    return true;
                }
            } else {
                if ($entry === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Plain case-sensitive `str_ends_with()` matcher used by legacy
     * `check_email()`. Diverges from {@see matchesRegexList()} in case
     * sensitivity and lack of subdomain expansion; same banlist can yield
     * different verdicts depending on which entry point a call site uses.
     */
    public static function matchesSuffixList(string $email, string $listValue): bool
    {
        $entries = array_filter(preg_split('/[\s]+/', $listValue) ?: []);
        foreach ($entries as $entry) {
            if (str_ends_with($email, (string) $entry)) {
                return true;
            }
        }

        return false;
    }
}
