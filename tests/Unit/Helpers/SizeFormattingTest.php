<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

/**
 * Covers size-formatting helpers from `include/functions.php`.
 *
 * These functions are used everywhere in the legacy UI to render torrent
 * sizes and traffic counters. They are pure (`number_format` / `floor`
 * arithmetic), so we lock down their boundary behaviour as a safety net
 * before touching the surrounding code.
 */
class SizeFormattingTest extends TestCase
{
    public function test_mksize_renders_kilobytes_below_one_megabyte_threshold(): void
    {
        $this->assertSame('0.00 KB', mksize(0));
        $this->assertSame('1.00 KB', mksize(1024));
        // 1000 * 1024 is the exclusive upper bound for KB rendering.
        // number_format() inserts a thousands separator at four digits.
        $this->assertSame('1,000.00 KB', mksize(1000 * 1024 - 1));
    }

    public function test_mksize_renders_megabytes(): void
    {
        $this->assertSame('1.00 MB', mksize(1048576));
        $this->assertSame('999.50 MB', mksize((int) (999.5 * 1048576)));
    }

    public function test_mksize_renders_gigabytes(): void
    {
        $this->assertSame('1.00 GB', mksize(1073741824));
    }

    public function test_mksize_renders_terabytes_with_three_decimals(): void
    {
        $this->assertSame('1.000 TB', mksize(1099511627776));
    }

    public function test_mksize_renders_petabytes_for_huge_inputs(): void
    {
        $this->assertSame('1.000 PB', mksize(1125899906842624));
    }

    public function test_mksizeint_floors_result_and_uses_lowercase_kb(): void
    {
        $this->assertSame('0 B', mksizeint(0));
        $this->assertSame('999 B', mksizeint(999));
        // mksizeint switches to KB at >=1000 and floors the result.
        $this->assertSame('0 kB', mksizeint(1000));
        $this->assertSame('1 kB', mksizeint(1024));
        $this->assertSame('1 MB', mksizeint(1048576));
    }

    public function test_mksizeint_clamps_negative_input_to_zero(): void
    {
        $this->assertSame('0 B', mksizeint(-1));
        $this->assertSame('0 B', mksizeint(-1024));
    }

    public function test_mksize_loose_uses_nbsp_separator(): void
    {
        $this->assertSame('1.00&nbsp;KB', mksize_loose(1024));
        $this->assertSame('1.00&nbsp;MB', mksize_loose(1048576));
    }

    public function test_mksize_compact_uses_br_separator(): void
    {
        $this->assertSame('1.00<br />KB', mksize_compact(1024));
        $this->assertSame('1.00<br />MB', mksize_compact(1048576));
    }

    public function test_getsize_int_converts_human_units_to_bytes(): void
    {
        $this->assertSame(1.0, getsize_int(1, 'B'));
        $this->assertSame(1024.0, getsize_int(1, 'K'));
        $this->assertSame(1048576.0, getsize_int(1, 'M'));
        $this->assertSame(1073741824.0, getsize_int(1, 'G'));
        $this->assertSame(1099511627776.0, getsize_int(1, 'T'));
        $this->assertSame(1125899906842624.0, getsize_int(1, 'P'));
    }

    public function test_getsize_int_defaults_to_gigabytes(): void
    {
        // Default unit is "G" — used when callers pass a raw number from
        // a quota-style setting field without specifying the unit.
        $this->assertSame(1073741824.0, getsize_int(1));
    }

    public function test_getsize_int_floors_fractional_input(): void
    {
        $this->assertSame(1.5 * 1048576, getsize_int(1.5, 'M'));
        // Floor only kicks in once arithmetic produces a non-integer.
        $this->assertSame(floor(1.5 * 1024), getsize_int(1.5, 'K'));
    }
}
