<?php

namespace Tests\Unit\Support;

use App\Support\Imdb;
use PHPUnit\Framework\TestCase;

class ImdbTest extends TestCase
{
    public function test_parse_id_returns_null_for_empty_input(): void
    {
        $this->assertNull(Imdb::parseId(null));
        $this->assertNull(Imdb::parseId(''));
        $this->assertNull(Imdb::parseId(false));
    }

    public function test_parse_id_returns_null_when_no_digits_found(): void
    {
        $this->assertNull(Imdb::parseId('https://example.com/no-digits/'));
        $this->assertNull(Imdb::parseId('tt'));
    }

    public function test_parse_id_extracts_id_from_full_imdb_url(): void
    {
        $this->assertSame(111161, Imdb::parseId('https://www.imdb.com/title/tt0111161/'));
        $this->assertSame(7286456, Imdb::parseId('https://www.imdb.com/title/tt7286456/'));
    }

    public function test_parse_id_extracts_id_from_tt_prefixed_id(): void
    {
        $this->assertSame(111161, Imdb::parseId('tt0111161'));
        $this->assertSame(7286456, Imdb::parseId('tt7286456'));
    }

    public function test_parse_id_returns_first_digit_run_for_bare_numeric_ids(): void
    {
        // 7+ digit numerics are returned as-is, no padding.
        $this->assertSame(7286456, Imdb::parseId('7286456'));
        $this->assertSame(7286456, Imdb::parseId(7286456));
    }

    public function test_parse_id_left_pads_short_numeric_ids_with_zeroes(): void
    {
        // Short numeric inputs (< 7 chars) are zero-padded to 7 chars
        // *before* digit extraction. The leading zeroes are then
        // dropped by the int cast on the extracted match. The net
        // effect is the same numeric value, but the padding is
        // load-bearing for the legacy behaviour we are mirroring.
        $this->assertSame(12345, Imdb::parseId('12345'));
        $this->assertSame(12345, Imdb::parseId(12345));
        $this->assertSame(1, Imdb::parseId('1'));
    }

    public function test_parse_id_strips_trailing_path_segments(): void
    {
        // preg_match returns only the FIRST digit run — anything
        // after that (slashes, query strings, ?ref_=…) is ignored.
        $this->assertSame(111161, Imdb::parseId('tt0111161/?ref_=fn_al_tt_1'));
        $this->assertSame(111161, Imdb::parseId('tt0111161 The Shawshank Redemption'));
    }

    public function test_build_url_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', Imdb::buildUrl(null));
        $this->assertSame('', Imdb::buildUrl(''));
    }

    public function test_build_url_renders_canonical_https_imdb_url(): void
    {
        $this->assertSame(
            'https://www.imdb.com/title/tt0111161/',
            Imdb::buildUrl('0111161'),
        );
        $this->assertSame(
            'https://www.imdb.com/title/tt7286456/',
            Imdb::buildUrl(7286456),
        );
    }

    public function test_build_url_preserves_legacy_php8_zero_semantics(): void
    {
        // Under PHP 8+ `0 == ""` is false, so a numeric 0 still
        // renders a URL. We pin this so a future "smart" refactor
        // that adds `$imdbId === 0` to the early-return doesn't
        // silently change legacy templates that pass `(int) $row['url']`.
        $this->assertSame('https://www.imdb.com/title/tt0/', Imdb::buildUrl(0));
        $this->assertSame('https://www.imdb.com/title/tt0/', Imdb::buildUrl('0'));
    }
}
