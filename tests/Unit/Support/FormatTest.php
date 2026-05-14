<?php

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    // ---------- size() / sizeCompact() / sizeLoose() ----------
    //
    // The three fractional formatters share the exact same numeric
    // body and bucket boundaries. We test the boundaries once via
    // size() and then spot-check that the other two only differ in
    // separator.

    public function test_size_kb_bucket(): void
    {
        // Anything strictly less than 1000*1024 bytes shows as KB.
        $this->assertSame('0.00 KB', Format::size(0));
        $this->assertSame('1.00 KB', Format::size(1024));
        $this->assertSame('999.00 KB', Format::size(999 * 1024));
    }

    public function test_size_mb_bucket(): void
    {
        // Exactly 1000*1024 crosses into MB. number_format rounds
        // 0.9765625 to 0.98, hence the funny first MB value.
        $this->assertSame('0.98 MB', Format::size(1000 * 1024));
        $this->assertSame('1.00 MB', Format::size(1048576));
    }

    public function test_size_gb_tb_pb_buckets(): void
    {
        $this->assertSame('1.00 GB', Format::size(1073741824));
        // TB and PB both have 3 decimal places, not 2.
        $this->assertSame('1.000 TB', Format::size(1099511627776));
        $this->assertSame('1.000 PB', Format::size(1125899906842624));
    }

    public function test_size_uses_1000x1024_boundary_not_1024_squared(): void
    {
        // Legacy quirk pinned: the KB→MB boundary is at 1000*1024 bytes,
        // NOT 1024*1024. That's why 1023.99 KB shows as KB but
        // 1024 KB jumps to "0.98 MB" instead of "1024.00 KB" or
        // "1.00 MB".
        $this->assertStringEndsWith(' KB', Format::size(1000 * 1024 - 1));
        $this->assertStringEndsWith(' MB', Format::size(1000 * 1024));
    }

    public function test_size_compact_uses_br_separator(): void
    {
        $this->assertSame('1.00<br />KB', Format::sizeCompact(1024));
        $this->assertSame('1.00<br />MB', Format::sizeCompact(1048576));
    }

    public function test_size_loose_uses_nbsp_separator(): void
    {
        $this->assertSame('1.00&nbsp;KB', Format::sizeLoose(1024));
        $this->assertSame('1.00&nbsp;MB', Format::sizeLoose(1048576));
    }

    // ---------- sizeInt() ----------

    public function test_size_int_floors_negative_to_zero_bytes(): void
    {
        $this->assertSame('0 B', Format::sizeInt(-1));
        $this->assertSame('0 B', Format::sizeInt(-99999));
    }

    public function test_size_int_b_bucket(): void
    {
        $this->assertSame('0 B', Format::sizeInt(0));
        $this->assertSame('999 B', Format::sizeInt(999));
        $this->assertSame('500 B', Format::sizeInt(500.7)); // truncated, not rounded
    }

    public function test_size_int_uses_lowercase_k_for_kb_bucket(): void
    {
        // Pinned legacy quirk: the kB bucket uses lowercase `k`,
        // while every other formatter (and the other size buckets
        // here) uses uppercase `K`. A "fix" that aligns to `KB`
        // would diverge from legacy output across ~200 call sites.
        $this->assertSame('1 kB', Format::sizeInt(1024));
        $this->assertSame('999 kB', Format::sizeInt(999 * 1024));
    }

    public function test_size_int_higher_buckets(): void
    {
        $this->assertSame('1 MB', Format::sizeInt(1048576));
        $this->assertSame('1 GB', Format::sizeInt(1073741824));
        $this->assertSame('1 TB', Format::sizeInt(1099511627776));
        $this->assertSame('1 PB', Format::sizeInt(1125899906842624));
    }

    // ---------- prettyTime() ----------

    public function test_pretty_time_returns_m_ss_below_one_hour(): void
    {
        $this->assertSame('0:00', Format::prettyTime(0));
        $this->assertSame('0:01', Format::prettyTime(1));
        $this->assertSame('0:59', Format::prettyTime(59));
        $this->assertSame('1:00', Format::prettyTime(60));
        $this->assertSame('59:59', Format::prettyTime(3599));
    }

    public function test_pretty_time_returns_h_mm_ss_below_one_day(): void
    {
        $this->assertSame('1:00:00', Format::prettyTime(3600));
        $this->assertSame('1:01:01', Format::prettyTime(3661));
        $this->assertSame('23:59:59', Format::prettyTime(86399));
    }

    public function test_pretty_time_includes_day_count_at_or_above_one_day(): void
    {
        // Day-label is interpolated literally between the day count
        // and the hh:mm:ss block. The default label is `"day(s)"`.
        $this->assertSame('1day(s)00:00:00', Format::prettyTime(86400));
        $this->assertSame('1day(s)00:01:01', Format::prettyTime(86461));
        $this->assertSame('2day(s)03:04:05', Format::prettyTime(2 * 86400 + 3 * 3600 + 4 * 60 + 5));
    }

    public function test_pretty_time_accepts_translated_day_label(): void
    {
        // The proxy in include/functions.php pulls
        // `$lang_functions['text_day']` and passes it here, so any
        // language-specific suffix survives the round-trip.
        $this->assertSame('1 дн.00:00:00', Format::prettyTime(86400, ' дн.'));
    }

    public function test_pretty_time_clamps_negative_input_to_zero(): void
    {
        $this->assertSame('0:00', Format::prettyTime(-1));
        $this->assertSame('0:00', Format::prettyTime(-99999));
    }

    public function test_pretty_time_rounds_fractional_seconds_before_bucketing(): void
    {
        // 59.5 rounds to 60, which bucketises into the next minute.
        // 59.4 rounds to 59, which stays in the 0:xx bucket.
        $this->assertSame('1:00', Format::prettyTime(59.5));
        $this->assertSame('0:59', Format::prettyTime(59.4));
    }
}
