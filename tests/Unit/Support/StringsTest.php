<?php

namespace Tests\Unit\Support;

use App\Support\Strings;
use PHPUnit\Framework\TestCase;

class StringsTest extends TestCase
{
    // ---------- pluralize() ----------

    public function test_pluralize_returns_singular_for_zero_or_one(): void
    {
        $this->assertSame('', Strings::pluralize(0, '', 's'));
        $this->assertSame('', Strings::pluralize(1, '', 's'));
        // Negative counts are also "singular" in the legacy contract
        // — the comparison is `> 1`, so -5 returns the singular form.
        $this->assertSame('', Strings::pluralize(-5, '', 's'));
    }

    public function test_pluralize_returns_plural_for_count_greater_than_one(): void
    {
        $this->assertSame('s', Strings::pluralize(2, '', 's'));
        $this->assertSame('s', Strings::pluralize(100, '', 's'));
    }

    public function test_pluralize_uses_strict_greater_than_threshold(): void
    {
        // Pinned legacy quirk: the threshold is `> 1`, not `>= 2`,
        // so fractional 1.5 already triggers the plural form. The
        // legacy `add_s()` is occasionally called with float ratios
        // (e.g. `add_s($mins)` where $mins is `floor(...) - hours*60`,
        // which is always integer in practice but the type isn't).
        $this->assertSame('s', Strings::pluralize(1.5, '', 's'));
        $this->assertSame('', Strings::pluralize(1.0, '', 's'));
    }

    public function test_pluralize_threads_language_aware_strings(): void
    {
        // The proxies for add_s() and is_or_are() pass language-aware
        // strings here. Verifies that we don't accidentally trim,
        // escape, or otherwise mutate the operator-supplied labels.
        $this->assertSame('are', Strings::pluralize(5, 'is', 'are'));
        $this->assertSame('is', Strings::pluralize(1, 'is', 'are'));
        $this->assertSame('ов', Strings::pluralize(5, 'а', 'ов'));
    }

    // ---------- randomCode() ----------

    public function test_random_code_length_matches_request(): void
    {
        $this->assertSame(0, strlen(Strings::randomCode(0)));
        $this->assertSame(1, strlen(Strings::randomCode(1)));
        $this->assertSame(6, strlen(Strings::randomCode(6)));
        $this->assertSame(32, strlen(Strings::randomCode(32)));
    }

    public function test_random_code_draws_only_from_unambiguous_alphabet(): void
    {
        // 21 chars: ABCDEFGH + PRMN + 1-9. NO 0, I, J, K, L, O, Q,
        // S, T, U, V, W, X, Y, Z, no lowercase. Pinned because a
        // future refactor that introduces lowercase or `0`/`O` would
        // silently break confirm-code readability for operators.
        $alphabet = 'ABCDEFGHPRMN123456789';
        $code = Strings::randomCode(200);
        $this->assertSame(200, strlen($code));
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $this->assertTrue(
                strpos($alphabet, $code[$i]) !== false,
                "Code contains disallowed character '{$code[$i]}' at index {$i}"
            );
        }
    }

    public function test_random_code_is_deterministic_under_srand(): void
    {
        // Pins the legacy `rand()` (not `random_int()`) source. If a
        // future refactor swaps to `random_int()` this test will fail
        // — re-seeding `random_int()` is not possible, so the new
        // helper must keep using `rand()` for backward compatibility.
        srand(12345);
        $a = Strings::randomCode(16);
        srand(12345);
        $b = Strings::randomCode(16);
        $this->assertSame($a, $b);
    }

    // ---------- hidden() ----------

    public function test_hidden_wraps_in_span(): void
    {
        $this->assertSame('<span class="hidden-text">1.2.3.4</span>', Strings::hidden('1.2.3.4'));
        $this->assertSame('<span class="hidden-text"></span>', Strings::hidden(''));
    }

    public function test_hidden_does_not_escape_input(): void
    {
        // Pinned legacy contract: hide_text() does not escape — every
        // existing call site already passes pre-escaped or
        // application-controlled text. A "safe" refactor that
        // wraps the input in htmlspecialchars() would double-escape
        // every existing call site.
        $this->assertSame(
            '<span class="hidden-text"><b>raw</b></span>',
            Strings::hidden('<b>raw</b>'),
        );
    }

    // ---------- highlight ----------

    public function test_highlight_wraps_single_match(): void
    {
        $this->assertSame(
            'before <b><font class="striking">match</font></b> after',
            Strings::highlight('match', 'before match after'),
        );
    }

    public function test_highlight_is_case_insensitive_but_preserves_matched_case(): void
    {
        $this->assertSame(
            'a <b><font class="striking">Foo</font></b> b <b><font class="striking">FOO</font></b> c',
            Strings::highlight('foo', 'a Foo b FOO c'),
        );
    }

    public function test_highlight_empty_needle_returns_subject_unchanged(): void
    {
        $this->assertSame('unchanged', Strings::highlight('', 'unchanged'));
    }

    public function test_highlight_no_match_returns_subject_unchanged(): void
    {
        $this->assertSame('nothing here', Strings::highlight('xyz', 'nothing here'));
    }

    public function test_highlight_respects_custom_wrappers(): void
    {
        $this->assertSame(
            '<<HIT>>',
            Strings::highlight('HIT', '<HIT>', '<', '>'),
        );
    }

    public function test_highlight_double_wraps_repeated_matches_legacy_quirk(): void
    {
        // Legacy quirk: each `stristr` iteration runs `str_replace` on
        // the current `$subject`, which already contains the wrapper
        // from the previous iteration. Two matches → two passes → the
        // first wrapper gets re-wrapped. A "safe" refactor would emit
        // each match wrapped only once, but call sites have been
        // rendering this nested HTML for years and we keep it.
        $this->assertSame(
            'a<b><font class="striking"><b><font class="striking">x</font></b></font></b>'
                .'b<b><font class="striking"><b><font class="striking">x</font></b></font></b>c',
            Strings::highlight('x', 'axbxc'),
        );
    }

    public function test_highlight_does_not_treat_needle_as_regex(): void
    {
        // Legacy contract: needle is a literal substring. A regex
        // metacharacter survives intact.
        $this->assertSame(
            'before <b><font class="striking">a.b</font></b> after',
            Strings::highlight('a.b', 'before a.b after'),
        );
    }

    // ---------- normalizeSearchTerm() ----------

    public function test_normalize_search_term_keeps_ascii_alphanumeric_intact(): void
    {
        $this->assertSame('hello world', Strings::normalizeSearchTerm('hello world'));
        $this->assertSame('foo123 bar', Strings::normalizeSearchTerm('foo123 bar'));
    }

    public function test_normalize_search_term_replaces_punctuation_with_single_spaces(): void
    {
        // Each non-alphanumeric byte becomes one space, then runs of
        // whitespace collapse to a single space. So `a.b!c` → `a b c`.
        $this->assertSame('a b c', Strings::normalizeSearchTerm('a.b!c'));
        $this->assertSame('a b c', Strings::normalizeSearchTerm('a,,b---c'));
    }

    public function test_normalize_search_term_strips_leading_and_trailing_whitespace(): void
    {
        $this->assertSame('foo bar', Strings::normalizeSearchTerm('   foo bar   '));
        $this->assertSame('foo', Strings::normalizeSearchTerm("\t\nfoo\r\n"));
    }

    public function test_normalize_search_term_collapses_internal_whitespace_runs(): void
    {
        $this->assertSame('foo bar baz', Strings::normalizeSearchTerm("foo  \t bar\n\nbaz"));
    }

    public function test_normalize_search_term_passes_through_empty_string(): void
    {
        $this->assertSame('', Strings::normalizeSearchTerm(''));
        $this->assertSame('', Strings::normalizeSearchTerm('   '));
    }

    public function test_normalize_search_term_treats_non_ascii_bytes_as_punctuation(): void
    {
        // Legacy contract: the regex `[^a-z0-9]` matches per-byte, not
        // per-codepoint. A multibyte Cyrillic letter (2 bytes in UTF-8)
        // becomes two spaces, which then collapse to one. Pinned here
        // so a future "fix" to use `\p{L}` doesn't silently change the
        // search index semantics.
        $this->assertSame('', Strings::normalizeSearchTerm('тест'));
        $this->assertSame('foo bar', Strings::normalizeSearchTerm('foo тест bar'));
    }
}
