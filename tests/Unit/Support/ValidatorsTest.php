<?php

namespace Tests\Unit\Support;

use App\Support\Validators;
use PHPUnit\Framework\TestCase;

class ValidatorsTest extends TestCase
{
    // ---------- isId() ----------

    public function test_is_id_accepts_positive_integers(): void
    {
        $this->assertTrue(Validators::isId(1));
        $this->assertTrue(Validators::isId(42));
        $this->assertTrue(Validators::isId(99999));
    }

    public function test_is_id_accepts_numeric_strings(): void
    {
        $this->assertTrue(Validators::isId('1'));
        $this->assertTrue(Validators::isId('42'));
        // Decimal-looking-but-integer-valued strings pass too —
        // `floor('1.0') == '1.0'` is true.
        $this->assertTrue(Validators::isId('1.0'));
    }

    public function test_is_id_rejects_zero_negative_and_non_integral(): void
    {
        $this->assertFalse(Validators::isId(0));
        $this->assertFalse(Validators::isId(-1));
        $this->assertFalse(Validators::isId(1.5));
        $this->assertFalse(Validators::isId('1.5'));
    }

    public function test_is_id_rejects_non_numeric_and_null(): void
    {
        $this->assertFalse(Validators::isId('abc'));
        $this->assertFalse(Validators::isId(''));
        $this->assertFalse(Validators::isId(null));
        $this->assertFalse(Validators::isId(false));
        $this->assertFalse(Validators::isId([]));
    }

    // ---------- isIpv4Format() ----------

    public function test_is_ipv4_format_accepts_valid_addresses(): void
    {
        $this->assertTrue(Validators::isIpv4Format('1.2.3.4'));
        $this->assertTrue(Validators::isIpv4Format('192.168.0.1'));
        $this->assertTrue(Validators::isIpv4Format('255.255.255.255'));
        $this->assertTrue(Validators::isIpv4Format('0.0.0.0'));
    }

    public function test_is_ipv4_format_rejects_out_of_range_octets(): void
    {
        $this->assertFalse(Validators::isIpv4Format('256.1.1.1'));
        $this->assertFalse(Validators::isIpv4Format('1.1.1.999'));
    }

    public function test_is_ipv4_format_rejects_garbage_and_partial(): void
    {
        $this->assertFalse(Validators::isIpv4Format(''));
        $this->assertFalse(Validators::isIpv4Format('not-an-ip'));
        $this->assertFalse(Validators::isIpv4Format('1.2.3'));
    }

    public function test_is_ipv4_format_is_intentionally_non_anchored(): void
    {
        // The legacy regex has no ^...$ anchors. Pinning this so a
        // future "tighter" refactor doesn't silently reject inputs
        // that legacy call sites in public/location.php currently
        // accept (e.g. tokens prefixed with whitespace from form input).
        $this->assertTrue(Validators::isIpv4Format('prefix 1.2.3.4 suffix'));
    }

    // ---------- isEmail() ----------

    public function test_is_email_accepts_valid_addresses(): void
    {
        $this->assertTrue(Validators::isEmail('a@b.com'));
        $this->assertTrue(Validators::isEmail('first.last+tag@sub.example.co.uk'));
    }

    public function test_is_email_rejects_invalid(): void
    {
        $this->assertFalse(Validators::isEmail(''));
        $this->assertFalse(Validators::isEmail('not-an-email'));
        $this->assertFalse(Validators::isEmail('@example.com'));
        $this->assertFalse(Validators::isEmail('a@'));
    }

    // ---------- isUsername() ----------

    public function test_is_username_accepts_alphanumeric_within_length_bounds(): void
    {
        $this->assertTrue(Validators::isUsername('abc'));
        $this->assertTrue(Validators::isUsername('User123'));
        $this->assertTrue(Validators::isUsername(str_repeat('a', 20)));
    }

    public function test_is_username_rejects_empty_string(): void
    {
        $this->assertFalse(Validators::isUsername(''));
    }

    public function test_is_username_rejects_disallowed_chars(): void
    {
        $this->assertFalse(Validators::isUsername('foo bar'));
        $this->assertFalse(Validators::isUsername('foo-bar'));
        $this->assertFalse(Validators::isUsername('foo.bar'));
        $this->assertFalse(Validators::isUsername('foo_bar'));
        $this->assertFalse(Validators::isUsername('Иван'));
    }

    public function test_is_username_rejects_lengths_outside_3_to_20(): void
    {
        // The length check intentionally runs AFTER the per-char
        // allowlist check. Both bounds are inclusive at the edges
        // (3 and 20 are valid, 2 and 21 are not).
        $this->assertFalse(Validators::isUsername('ab'));
        $this->assertFalse(Validators::isUsername(str_repeat('a', 21)));
        $this->assertTrue(Validators::isUsername(str_repeat('a', 3)));
        $this->assertTrue(Validators::isUsername(str_repeat('a', 20)));
    }

    // ---------- isFileName() ----------

    public function test_is_file_name_accepts_safe_names(): void
    {
        $this->assertTrue(Validators::isFileName('image.png'));
        $this->assertTrue(Validators::isFileName('audio/lossless/cssfile.css'));
        $this->assertTrue(Validators::isFileName('a_b.c'));
    }

    public function test_is_file_name_rejects_unsafe_chars(): void
    {
        $this->assertFalse(Validators::isFileName('UPPER.png'));   // uppercase
        $this->assertFalse(Validators::isFileName('foo bar.png')); // space
        $this->assertFalse(Validators::isFileName('foo-bar.png')); // hyphen
    }

    public function test_is_file_name_does_not_protect_against_path_traversal(): void
    {
        // Legacy quirk pinned deliberately: the allowlist contains
        // `.` and `/`, so `../etc/pass`-shaped traversal strings
        // still pass. Every call site already canonicalises or
        // joins onto a known prefix before reading from disk, but
        // a future refactor that drops `.` or `/` from the allowlist
        // would silently break category folders like `audio/lossless/`,
        // so the contract is preserved.
        $this->assertTrue(Validators::isFileName('../etc/pass'));
    }

    public function test_is_file_name_empty_string_is_legacy_true(): void
    {
        // Legacy quirk: empty string passes because the for-loop body
        // never executes. Pinned so a future refactor doesn't tighten
        // this silently (every observed call site guards with
        // `if (!$x || !valid_file_name($x))` style checks).
        $this->assertTrue(Validators::isFileName(''));
    }

    // ---------- isClassName() ----------

    public function test_is_class_name_accepts_lowercase_identifiers(): void
    {
        $this->assertTrue(Validators::isClassName('cat'));
        $this->assertTrue(Validators::isClassName('cat_class_1'));
        $this->assertTrue(Validators::isClassName('a'));
    }

    public function test_is_class_name_rejects_invalid_first_char(): void
    {
        $this->assertFalse(Validators::isClassName('1cat'));
        $this->assertFalse(Validators::isClassName('_cat'));
        $this->assertFalse(Validators::isClassName('Cat'));
    }

    public function test_is_class_name_rejects_invalid_trailing_chars(): void
    {
        $this->assertFalse(Validators::isClassName('cat-class'));
        $this->assertFalse(Validators::isClassName('cat.class'));
        $this->assertFalse(Validators::isClassName('catClass')); // uppercase mid-string
    }

    public function test_is_class_name_empty_string_is_legacy_true(): void
    {
        // Pinned legacy quirk — see the doc comment in Validators.
        // Every call site guards with `if ($class_name && !valid_class_name(...))`
        // so the empty-string case doesn't actually reach this validator
        // in practice, but the contract is preserved deliberately.
        $this->assertTrue(Validators::isClassName(''));
    }
}
