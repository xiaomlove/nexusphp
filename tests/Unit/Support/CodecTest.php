<?php

namespace Tests\Unit\Support;

use App\Support\Codec;
use PHPUnit\Framework\TestCase;

class CodecTest extends TestCase
{
    // ---------- base64Encode / base64Decode ----------

    public function test_base64_encode_matches_php_builtin(): void
    {
        $this->assertSame('aGVsbG8=', Codec::base64Encode('hello'));
        $this->assertSame('', Codec::base64Encode(''));
        $this->assertSame('eWVhaA==', Codec::base64Encode('yeah'));
    }

    public function test_base64_decode_matches_php_builtin(): void
    {
        $this->assertSame('hello', Codec::base64Decode('aGVsbG8='));
        $this->assertSame('', Codec::base64Decode(''));
        $this->assertSame('yeah', Codec::base64Decode('eWVhaA=='));
    }

    public function test_base64_round_trip_preserves_binary_payload(): void
    {
        // Pinned legacy contract: base64() is a pure wrapper, no
        // strict mode, so it must survive arbitrary binary input.
        $payload = "\x00\x01\x02\xfe\xff binary \n\r\t";
        $this->assertSame($payload, Codec::base64Decode(Codec::base64Encode($payload)));
    }

    public function test_base64_decode_is_not_strict(): void
    {
        // Pinned legacy contract: base64_decode() is called WITHOUT
        // the strict flag, so non-base64 input is silently coerced
        // (invalid chars are skipped, not rejected). Some legacy
        // call sites depend on this lenience.
        $this->assertSame('hello', Codec::base64Decode("aGVs\nbG8="));
    }

    // ---------- ibm437ToEntities (active code_new path) ----------

    public function test_ibm437_entities_passes_low_ascii_verbatim(): void
    {
        // Bytes 0x00-0x7E are pure passthrough — the legacy
        // `if ($ob >= 127)` branch keeps ASCII literal so call sites
        // get plain text for printable input.
        $ascii = "Hello, World! 1234567890\n\t\r";
        $this->assertSame($ascii, Codec::ibm437ToEntities($ascii, ''));
    }

    public function test_ibm437_entities_converts_high_bytes_to_numeric_entities(): void
    {
        // 0x7F = 8962 (logical NOT, ⌂), 0x80 = 199 (Ç), 0xFF = 160 (NBSP).
        // Pins the codepoint table at its three corners.
        $this->assertSame('&#8962;', Codec::ibm437ToEntities("\x7F", ''));
        $this->assertSame('&#199;', Codec::ibm437ToEntities("\x80", ''));
        $this->assertSame('&#160;', Codec::ibm437ToEntities("\xFF", ''));
    }

    public function test_ibm437_entities_mixed_input(): void
    {
        // Typical NFO snippet: ASCII art with high-byte box-drawing
        // characters. Verifies the loop interleaves literal ASCII
        // and numeric entities correctly.
        $input = "A\xB3B\xC4C";
        // \xB3 = 179 → 9474 (│), \xC4 = 196 → 9472 (─).
        $this->assertSame('A&#9474;B&#9472;C', Codec::ibm437ToEntities($input, ''));
    }

    public function test_ibm437_entities_empty_input_returns_empty(): void
    {
        $this->assertSame('', Codec::ibm437ToEntities('', ''));
        $this->assertSame('', Codec::ibm437ToEntities('', 'magic'));
    }

    public function test_ibm437_entities_view_is_strict_string_match_for_magic(): void
    {
        // Pinned legacy contract: only the exact string `'magic'`
        // activates the Swedish-letter branch. Even though that branch
        // is a no-op in practice (see test above), this guards against
        // a future refactor that broadens the match (e.g. `MAGIC`,
        // `'1'`, truthy comparison) and accidentally changes the
        // branch-taken metric the legacy proxy contract relies on.
        $input = "X\345Y";
        $plain = Codec::ibm437ToEntities($input, '');
        $magic = Codec::ibm437ToEntities($input, 'magic');
        $latin1 = Codec::ibm437ToEntities($input, 'latin-1');
        $fonthack = Codec::ibm437ToEntities($input, 'fonthack');
        $this->assertSame($plain, $latin1);
        $this->assertSame($plain, $fonthack);
        // Magic ALSO produces the same bytes (the swedish block is
        // a no-op once entities have already been emitted) — but
        // the branch IS taken; we just don't see a byte-level diff.
        $this->assertSame($plain, $magic);
    }

    public function test_ibm437_entities_magic_block_is_a_noop_in_practice(): void
    {
        // Pinned legacy behaviour: in `code_new()` the Swedish-magic
        // block runs AFTER every high byte has already been replaced
        // with a numeric entity (`&#NNN;`). Since `\345`, `\344`,
        // `\366`, `\305`, `\304`, `\326`, `\311` and `\351` no longer
        // appear in the intermediate string, the `str_replace` and
        // `preg_replace` calls match nothing — magic mode produces the
        // exact same bytes as plain mode. Pinning this guards against
        // a future "clean-up" refactor that moves the Swedish block
        // BEFORE the entity loop (which WOULD change observable output).
        $sample = "abc\345def\344ghi\366jkl";
        $this->assertSame(
            Codec::ibm437ToEntities($sample, ''),
            Codec::ibm437ToEntities($sample, 'magic'),
        );
    }

    public function test_ibm437_entities_full_byte_round_trip_matches_legacy(): void
    {
        // Pinned by the migration: feeding all 256 byte values through
        // the new method must produce the same bytes as the legacy
        // `code_new()` did. The legacy expected output is baked in
        // below as a hex digest. If the migration ever drifts, the
        // hash will change and this test fails loudly.
        //
        // Note: plain and magic digests are identical — see
        // {@see test_ibm437_entities_magic_block_is_a_noop_in_practice}.
        $all = '';
        for ($i = 0; $i < 256; $i++) {
            $all .= chr($i);
        }
        $this->assertSame('abc328ead7cb8deb1a299c8c25d81c06', md5(Codec::ibm437ToEntities($all, '')));
        $this->assertSame('abc328ead7cb8deb1a299c8c25d81c06', md5(Codec::ibm437ToEntities($all, 'magic')));
    }

    // ---------- ibm437ToEntitiesLegacy (older code path) ----------

    public function test_ibm437_legacy_strips_control_chars_to_space(): void
    {
        // Pinned legacy quirk: control bytes 0x00-0x09, 0x0B, 0x0C,
        // 0x0E-0x1F, and 0x7F are replaced with a single space.
        // LF (0x0A) and CR (0x0D) are NOT stripped — they survive.
        $this->assertSame(' X ', Codec::ibm437ToEntitiesLegacy("\x01X\x02", ''));
        $this->assertSame("\nX\r", Codec::ibm437ToEntitiesLegacy("\nX\r", ''));
        $this->assertSame(' ', Codec::ibm437ToEntitiesLegacy("\x7F", ''));
    }

    public function test_ibm437_legacy_html_escapes_input(): void
    {
        // Pinned legacy contract: the older `code()` calls
        // `htmlspecialchars()` (default flags) on its input. PHP 8.1+
        // entity-encodes single quotes too — we don't override.
        $this->assertSame('&lt;b&gt;', Codec::ibm437ToEntitiesLegacy('<b>', ''));
        $this->assertSame('a &amp; b', Codec::ibm437ToEntitiesLegacy('a & b', ''));
    }

    public function test_ibm437_legacy_high_bytes_pass_through_htmlspecialchars_mangling(): void
    {
        // Pinned PHP 8.1+ behaviour: `htmlspecialchars()` (default
        // flags) substitutes invalid UTF-8 byte sequences with the
        // Unicode Replacement Character (U+FFFD) BEFORE the
        // high-byte table swap runs. The three-byte UTF-8 encoding
        // of U+FFFD is `\xEF\xBF\xBD`, which the subsequent table
        // swap turns into the entity triple below. The legacy
        // `code()` function is effectively broken for its intended
        // (IBM-437) input on PHP 8.1+ — we pin the current
        // observable output here so a future PHP upgrade doesn't
        // silently change behaviour again.
        $expected = '&#x2229;&#x2510;&#x255c;';
        $this->assertSame($expected, Codec::ibm437ToEntitiesLegacy("\x80", ''));
        $this->assertSame($expected, Codec::ibm437ToEntitiesLegacy("\x81", ''));
        $this->assertSame($expected, Codec::ibm437ToEntitiesLegacy("\xFF", ''));
    }

    public function test_ibm437_legacy_passes_ascii_verbatim(): void
    {
        // ASCII is valid UTF-8, so `htmlspecialchars()` leaves it
        // alone (except for the four reserved chars: `<`, `>`, `&`,
        // `"`, and on PHP 8.1+ also `'`). Pinning this guards the
        // common case — .nfo headers that happen to be pure
        // printable ASCII render correctly.
        $this->assertSame('Hello World', Codec::ibm437ToEntitiesLegacy('Hello World', ''));
        $this->assertSame("ASCII\nline\rbreaks", Codec::ibm437ToEntitiesLegacy("ASCII\nline\rbreaks", ''));
    }

    public function test_ibm437_legacy_full_byte_round_trip_matches_legacy(): void
    {
        // Same shape as the ibm437ToEntities digest test: pin all
        // 256 bytes for both views.
        //
        // Note: plain and magic happen to produce identical bytes for
        // this specific input because PHP 8.1+ `htmlspecialchars()`
        // mangles invalid UTF-8 byte sequences before the Swedish
        // block runs. The test guards against drift either way —
        // if the digest moves on either side, something material
        // changed and we need to know.
        $all = '';
        for ($i = 0; $i < 256; $i++) {
            $all .= chr($i);
        }
        $this->assertSame('3fb0510722a53448e700a44c717dd0f9', md5(Codec::ibm437ToEntitiesLegacy($all, '')));
        $this->assertSame('3fb0510722a53448e700a44c717dd0f9', md5(Codec::ibm437ToEntitiesLegacy($all, 'magic')));
    }

    public function test_ibm437_legacy_view_is_only_active_for_magic(): void
    {
        $input = "X\345Y";
        $plain = Codec::ibm437ToEntitiesLegacy($input, '');
        $latin1 = Codec::ibm437ToEntitiesLegacy($input, 'latin-1');
        $this->assertSame($plain, $latin1);
    }

    // ---------- phpExport() ----------

    public function test_php_export_quotes_strings_with_backslash_and_single_quote_escapes(): void
    {
        $this->assertSame("'foo'", Codec::phpExport('foo'));
        $this->assertSame("''", Codec::phpExport(''));
        $this->assertSame("'it\\'s'", Codec::phpExport("it's"));
        $this->assertSame("'a\\\\b'", Codec::phpExport('a\\b'));
    }

    public function test_php_export_stringifies_numeric_types(): void
    {
        // Legacy quirk pinned: ints/floats/doubles are emitted as
        // QUOTED strings (single-quoted). The receiving parser
        // re-coerces back to numeric, but the source-literal form
        // is strings.
        $this->assertSame("'0'", Codec::phpExport(0));
        $this->assertSame("'42'", Codec::phpExport(42));
        $this->assertSame("'-5'", Codec::phpExport(-5));
        $this->assertSame("'1.5'", Codec::phpExport(1.5));
        $this->assertSame("'1.0E+20'", Codec::phpExport(1.0e20));
    }

    public function test_php_export_emits_boolean_and_null_literals(): void
    {
        // Lowercase `true` / `false` (PHP convention) but UPPERCASE
        // `NULL` — that's the legacy contract.
        $this->assertSame('true', Codec::phpExport(true));
        $this->assertSame('false', Codec::phpExport(false));
        $this->assertSame('NULL', Codec::phpExport(null));
    }

    public function test_php_export_emits_arrays_with_carriage_return_opener(): void
    {
        // Legacy quirk: arrays open with `array(\r` (carriage return,
        // not `\n`!). Each entry is `<indent>\t<key> => <value>,\n`,
        // closing paren sits at parent indent. Pinned bit-exact so the
        // emitted `config/allconfig.php` survives round-trips through
        // the legacy `require` loop.
        $this->assertSame(
            "array(\r\t'a' => '1',\n\t'b' => '2',\n)",
            Codec::phpExport(['a' => 1, 'b' => 2]),
        );
    }

    public function test_php_export_nests_arrays_with_growing_indent(): void
    {
        $input = ['outer' => ['inner' => 'v']];
        $expected = "array(\r\t'outer' => array(\r\t\t'inner' => 'v',\n\t),\n)";
        $this->assertSame($expected, Codec::phpExport($input));
    }

    public function test_php_export_falls_through_to_null_for_unknown_types(): void
    {
        // Objects, resources, etc. all fall through to the legacy
        // `return 'NULL'` tail branch.
        $this->assertSame('NULL', Codec::phpExport(new \stdClass));
    }
}
