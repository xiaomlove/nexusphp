<?php

namespace Tests\Unit\Support;

use App\Support\Email;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    // ---------- sanitizeForDisplay ----------

    public function test_sanitize_for_display_strips_angle_brackets(): void
    {
        $this->assertSame('user@example.com', Email::sanitizeForDisplay('<user@example.com>'));
        $this->assertSame('user@example.com', Email::sanitizeForDisplay('user@example.com<'));
        $this->assertSame('user@example.com', Email::sanitizeForDisplay('user@>example.com'));
    }

    public function test_sanitize_for_display_strips_legacy_magic_quotes_artefacts(): void
    {
        // Pinned legacy behaviour: the literal sequences `\'`, `\"`, `\\`
        // (a backslash followed by a quote / another backslash) are stripped.
        // These came from PHP's `magic_quotes_gpc` and cannot be auto-generated
        // any more on PHP 8+, but the strip is preserved defensively.
        $this->assertSame('useratexample.com', Email::sanitizeForDisplay("user\\'at\\\"example.com"));
        $this->assertSame('userexample.com', Email::sanitizeForDisplay('user\\\\example.com'));
    }

    public function test_sanitize_for_display_leaves_safe_characters_alone(): void
    {
        $this->assertSame(
            'first.last+tag@sub.example.co.uk',
            Email::sanitizeForDisplay('first.last+tag@sub.example.co.uk'),
        );
    }

    public function test_sanitize_for_display_passes_through_empty_string(): void
    {
        $this->assertSame('', Email::sanitizeForDisplay(''));
    }

    public function test_sanitize_for_display_does_not_validate(): void
    {
        // Pinned: strip-only, no rejection. The legacy contract is that
        // safe_email() returns whatever's left after the strip — even if
        // the result is no longer a valid address. check_email() is the
        // validator.
        $this->assertSame('not-an-email', Email::sanitizeForDisplay('not-an-email'));
        // All `<` and `>` are stripped; the result is the surrounding letters
        // with no separator — i.e. a still-invalid string, but the function
        // doesn't reject it because validation is `check_email()`'s job.
        $this->assertSame('onlychars', Email::sanitizeForDisplay('only<><><<><>chars'));
    }

    // ---------- charsetFor ----------

    public function test_charset_for_chinese_folders_returns_gbk(): void
    {
        $this->assertSame('gbk', Email::charsetFor('chs'));
        $this->assertSame('gbk', Email::charsetFor('cht'));
    }

    public function test_charset_for_other_folders_falls_back_to_utf8(): void
    {
        $this->assertSame('utf-8', Email::charsetFor('eng'));
        $this->assertSame('utf-8', Email::charsetFor('jpn'));
        $this->assertSame('utf-8', Email::charsetFor('rus'));
        $this->assertSame('utf-8', Email::charsetFor(''));
    }

    public function test_charset_for_is_strictly_case_sensitive(): void
    {
        // Pinned legacy behaviour: `$lang == 'chs'` is a strict string
        // equality check after lookup, so capitalised forms fall back
        // to utf-8. This is intentional — the language-folder cookie
        // is always set in lowercase by the legacy bootstrap.
        $this->assertSame('utf-8', Email::charsetFor('CHS'));
        $this->assertSame('utf-8', Email::charsetFor('Cht'));
        $this->assertSame('utf-8', Email::charsetFor('chS'));
    }

    // ---------- convertCharset ----------

    public function test_convert_charset_is_pass_through_for_ascii_into_utf8(): void
    {
        $this->assertSame('hello world', Email::convertCharset('eng', 'hello world'));
        $this->assertSame('', Email::convertCharset('eng', ''));
    }

    public function test_convert_charset_to_gbk_uses_legacy_iconv_with_ignore(): void
    {
        // Pin the contract: `//IGNORE` means un-mappable codepoints are
        // dropped silently, not flagged as an error. Round-trip an ASCII
        // payload via gbk back to utf-8 must produce the original.
        $original = 'plain ASCII payload';
        $gbk = Email::convertCharset('chs', $original);
        $this->assertNotFalse($gbk);
        $this->assertSame($original, iconv('gbk', 'utf-8', $gbk));
    }

    public function test_convert_charset_silently_drops_unmappable_codepoints(): void
    {
        // `chs` → gbk. The 4-byte UTF-8 emoji 🐳 (U+1F433) has no GBK
        // mapping; `//IGNORE` must silently drop it rather than fail.
        $gbk = Email::convertCharset('chs', 'whale 🐳 here');
        $this->assertNotFalse($gbk);
        // We can't assert the exact byte payload (gbk-encoded ASCII is
        // the same as the ASCII source), but the round-trip must contain
        // the surrounding text and NOT the emoji.
        $roundtrip = iconv('gbk', 'utf-8', $gbk);
        $this->assertStringContainsString('whale', $roundtrip);
        $this->assertStringContainsString('here', $roundtrip);
        $this->assertStringNotContainsString('🐳', $roundtrip);
    }

    // ---------- isWellFormed ----------

    public function test_is_well_formed_accepts_common_addresses(): void
    {
        $this->assertTrue(Email::isWellFormed('user@example.com'));
        $this->assertTrue(Email::isWellFormed('first.last@example.com'));
        $this->assertTrue(Email::isWellFormed('user+tag@example.com'));
        $this->assertTrue(Email::isWellFormed('user-name@sub.example.co.uk'));
        $this->assertTrue(Email::isWellFormed('123@4.com'));
    }

    public function test_is_well_formed_rejects_leading_punctuation_in_local_part(): void
    {
        // Pinned legacy quirk: stricter than RFC 5322. The local part
        // MUST start with `[A-Za-z0-9]`. Real-world senders sometimes
        // try `+alias@…` or `.dot@…` — the legacy rejects them and so
        // do we.
        $this->assertFalse(Email::isWellFormed('+tag@example.com'));
        $this->assertFalse(Email::isWellFormed('-dash@example.com'));
        $this->assertFalse(Email::isWellFormed('.dot@example.com'));
        $this->assertFalse(Email::isWellFormed('_under@example.com'));
    }

    public function test_is_well_formed_rejects_single_label_domain(): void
    {
        // Pinned: domain MUST have at least one dot. `user@localhost` is
        // RFC-valid but legacy-invalid.
        $this->assertFalse(Email::isWellFormed('user@localhost'));
        $this->assertFalse(Email::isWellFormed('user@example'));
    }

    public function test_is_well_formed_rejects_obvious_garbage(): void
    {
        $this->assertFalse(Email::isWellFormed(''));
        $this->assertFalse(Email::isWellFormed('not-an-email'));
        $this->assertFalse(Email::isWellFormed('@example.com'));
        $this->assertFalse(Email::isWellFormed('user@'));
        $this->assertFalse(Email::isWellFormed('user@@example.com'));
        $this->assertFalse(Email::isWellFormed('user @example.com'));
        $this->assertFalse(Email::isWellFormed("user\n@example.com"));
    }

    public function test_is_well_formed_rejects_domain_label_starting_with_punctuation(): void
    {
        // Every domain label must start with `[A-Za-z0-9]`, including
        // the TLD label. So `example.-com` and `sub.-example.com` fail.
        $this->assertFalse(Email::isWellFormed('user@example.-com'));
        $this->assertFalse(Email::isWellFormed('user@-example.com'));
        $this->assertFalse(Email::isWellFormed('user@sub.-example.com'));
    }

    // ---------- matchesRegexList ----------

    public function test_matches_regex_list_against_empty_list_returns_false(): void
    {
        $this->assertFalse(Email::matchesRegexList('user@example.com', ''));
        $this->assertFalse(Email::matchesRegexList('user@example.com', "   \t\n  "));
    }

    public function test_matches_regex_list_at_host_pattern_matches_exact_host(): void
    {
        $this->assertTrue(Email::matchesRegexList('user@example.com', '@example.com'));
    }

    public function test_matches_regex_list_at_host_pattern_matches_subdomains(): void
    {
        // Legacy rewrite `@` -> `[@\.]` makes any subdomain a hit.
        $this->assertTrue(Email::matchesRegexList('user@sub.example.com', '@example.com'));
        $this->assertTrue(Email::matchesRegexList('user@deep.sub.example.com', '@example.com'));
    }

    public function test_matches_regex_list_at_host_pattern_does_not_match_unrelated_host(): void
    {
        $this->assertFalse(Email::matchesRegexList('user@other.com', '@example.com'));
    }

    public function test_matches_regex_list_is_case_insensitive(): void
    {
        $this->assertTrue(Email::matchesRegexList('USER@Example.COM', '@example.com'));
        $this->assertTrue(Email::matchesRegexList('user@example.com', '@EXAMPLE.COM'));
    }

    public function test_matches_regex_list_user_at_host_entry_never_matches_legacy_quirk(): void
    {
        // strstr($entry,'@') swallows entries with `@` before the exact-match branch.
        $this->assertFalse(Email::matchesRegexList('user@example.com', 'user@example.com'));
    }

    public function test_matches_regex_list_user_at_entry_never_matches_legacy_quirk(): void
    {
        // Trailing-`@` entries are unreachable in the legacy control-flow.
        $this->assertFalse(Email::matchesRegexList('user@example.com', 'user@'));
    }

    public function test_matches_regex_list_naked_entry_only_matches_naked_input_exactly(): void
    {
        $this->assertTrue(Email::matchesRegexList('spam', 'spam'));
        $this->assertFalse(Email::matchesRegexList('spam@example.com', 'spam'));
        $this->assertFalse(Email::matchesRegexList('spammer', 'spam'));
    }

    public function test_matches_regex_list_splits_on_any_whitespace_run(): void
    {
        $list = "@evil.com\t\t@bad.example.net\n\n  @baz.org   ";
        $this->assertTrue(Email::matchesRegexList('a@evil.com', $list));
        $this->assertTrue(Email::matchesRegexList('b@bad.example.net', $list));
        $this->assertTrue(Email::matchesRegexList('c@baz.org', $list));
        $this->assertFalse(Email::matchesRegexList('d@good.org', $list));
    }

    public function test_matches_regex_list_escapes_dots_in_entries(): void
    {
        $this->assertFalse(Email::matchesRegexList('userXexample.com', '@example.com'));
    }

    public function test_matches_regex_list_returns_on_first_hit(): void
    {
        $this->assertTrue(Email::matchesRegexList('user@example.com', '@other.org @example.com @third.net'));
    }

    public function test_matches_regex_list_trims_input_email(): void
    {
        $this->assertTrue(Email::matchesRegexList('  USER@EXAMPLE.COM  ', '@example.com'));
    }

    // ---------- matchesSuffixList ----------

    public function test_matches_suffix_list_matches_literal_suffix(): void
    {
        $this->assertTrue(Email::matchesSuffixList('user@spam.tld', '@spam.tld'));
        $this->assertTrue(Email::matchesSuffixList('foo@spam.tld', '@spam.tld'));
    }

    public function test_matches_suffix_list_is_plain_suffix_not_subdomain_match_legacy_quirk(): void
    {
        // Plain str_ends_with — no subdomain expansion (diverges from matchesRegexList).
        $this->assertFalse(Email::matchesSuffixList('foo@bar.spam.tld', '@spam.tld'));
    }

    public function test_matches_suffix_list_does_not_match_unrelated(): void
    {
        $this->assertFalse(Email::matchesSuffixList('user@other.tld', '@spam.tld'));
    }

    public function test_matches_suffix_list_is_case_sensitive_legacy_quirk(): void
    {
        // check_email() does NOT lowercase before str_ends_with — diverges from matchesRegexList.
        $this->assertFalse(Email::matchesSuffixList('user@SPAM.TLD', '@spam.tld'));
    }

    public function test_matches_suffix_list_with_empty_list_returns_false(): void
    {
        $this->assertFalse(Email::matchesSuffixList('user@example.com', ''));
        $this->assertFalse(Email::matchesSuffixList('user@example.com', "   \t\n  "));
    }

    public function test_matches_suffix_list_supports_user_at_host_entries(): void
    {
        // Unlike matchesRegexList(), this matcher accepts literal user@host entries.
        $this->assertTrue(Email::matchesSuffixList('user@example.com', 'user@example.com'));
    }

    public function test_matches_suffix_list_short_circuits_on_first_hit(): void
    {
        $this->assertTrue(Email::matchesSuffixList('foo@target.com', '@other.org @target.com @third.net'));
    }
}
