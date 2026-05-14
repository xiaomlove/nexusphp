<?php

namespace Tests\Unit\Support;

use App\Support\Time;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    private const LABELS = [
        'year' => 'year',
        'year_short' => 'y',
        'month' => 'month',
        'month_short' => 'mo',
        'day' => 'day',
        'day_short' => 'd',
        'hour' => 'hour',
        'hour_short' => 'h',
        'min' => 'min',
        'min_short' => 'm',
        'plural_suffix' => 's',
    ];

    // ---------- microtimeFloat() ----------

    public function test_microtime_float_returns_value_close_to_microtime_true(): void
    {
        // Allow a generous tolerance because two consecutive
        // syscalls can drift by a few microseconds.
        $expected = microtime(true);
        $actual = Time::microtimeFloat();
        $this->assertEqualsWithDelta($expected, $actual, 0.5);
    }

    public function test_microtime_float_is_seconds_since_epoch(): void
    {
        $value = Time::microtimeFloat();
        // Sanity: should be a sane Unix timestamp (after 2020-01-01).
        $this->assertGreaterThan(1_577_836_800.0, $value);
    }

    // ---------- deadThreshold() ----------

    public function test_dead_threshold_subtracts_floor_of_1_3x_interval(): void
    {
        $now = 1_700_000_000;
        // 1800 * 1.3 = 2340; floor() leaves it unchanged.
        $this->assertSame($now - 2340, Time::deadThreshold(1800, $now));
        // 60 * 1.3 = 78; floor() leaves it unchanged.
        $this->assertSame($now - 78, Time::deadThreshold(60, $now));
    }

    public function test_dead_threshold_floors_fractional_product(): void
    {
        // 7 * 1.3 = 9.1; floor() -> 9. Pinned because a future
        // refactor that uses round() would shift the cutoff by a
        // second and could orphan peers prematurely.
        $now = 1_700_000_000;
        $this->assertSame($now - 9, Time::deadThreshold(7, $now));
    }

    public function test_dead_threshold_handles_zero_interval(): void
    {
        $now = 1_700_000_000;
        $this->assertSame($now, Time::deadThreshold(0, $now));
    }

    public function test_dead_threshold_uses_time_when_now_omitted(): void
    {
        $before = time();
        $result = Time::deadThreshold(1800);
        $after = time();
        // Result should be within [before - 2340, after - 2340].
        $this->assertGreaterThanOrEqual($before - 2340, $result);
        $this->assertLessThanOrEqual($after - 2340, $result);
    }

    // ---------- elapsedSince() ----------

    public function test_elapsed_returns_less_than_min_for_zero_delta(): void
    {
        $this->assertSame('&lt; 1min', Time::elapsedSince(1000, 1000, self::LABELS));
        $this->assertSame('&lt; 1m', Time::elapsedSince(1000, 1000, self::LABELS, shortUnit: true));
    }

    public function test_elapsed_returns_less_than_min_for_under_60_seconds(): void
    {
        // 59 seconds is still "< 1min".
        $this->assertSame('&lt; 1min', Time::elapsedSince(0, 59, self::LABELS));
    }

    public function test_elapsed_renders_minutes_only(): void
    {
        // 5 minutes exactly.
        $this->assertSame('5mins', Time::elapsedSince(0, 5 * 60, self::LABELS));
        // 1 minute uses singular form (no plural suffix).
        $this->assertSame('1min', Time::elapsedSince(0, 60, self::LABELS));
    }

    public function test_elapsed_renders_hours_and_minutes(): void
    {
        // 2h 30m
        $delta = 2 * 3600 + 30 * 60;
        $this->assertSame('2hours&nbsp;30mins', Time::elapsedSince(0, $delta, self::LABELS));
        // 1h 1m — both singular
        $this->assertSame('1hour&nbsp;1min', Time::elapsedSince(0, 3600 + 60, self::LABELS));
        // 1h 0m — minute is singular (zero is not > 1)
        $this->assertSame('1hour&nbsp;0min', Time::elapsedSince(0, 3600, self::LABELS));
    }

    public function test_elapsed_renders_days_and_hours(): void
    {
        // 1 day 5 hours
        $delta = 86400 + 5 * 3600;
        $this->assertSame('1day&nbsp;5hours', Time::elapsedSince(0, $delta, self::LABELS));
    }

    public function test_elapsed_renders_months_and_days(): void
    {
        // 31 days -> 1 month 1 day. Legacy buckets months as
        // `floor(days / 30)`, so day 31 flips to "1 month".
        $delta = 31 * 86400;
        $this->assertSame('1month&nbsp;1day', Time::elapsedSince(0, $delta, self::LABELS));
    }

    public function test_elapsed_renders_years_and_months_for_one_full_year(): void
    {
        // 365 days exactly. Legacy quirk: months and years are
        // *both* computed from raw days, so 365 days renders as
        // "1year 0month", not "1year".
        $delta = 365 * 86400;
        $this->assertSame('1year&nbsp;0month', Time::elapsedSince(0, $delta, self::LABELS));
    }

    public function test_elapsed_renders_years_and_months_for_year_and_a_half(): void
    {
        // 18 months ~= 540 days. floor(540 / 365) = 1 year.
        // floor(540 / 30) = 18 months; months -= years * 12 -> 6 months.
        $delta = 540 * 86400;
        $this->assertSame('1year&nbsp;6months', Time::elapsedSince(0, $delta, self::LABELS));
    }

    public function test_elapsed_uses_abs_for_future_timestamps(): void
    {
        // A timestamp 5 minutes in the future should render
        // identically to one 5 minutes in the past.
        $this->assertSame(
            Time::elapsedSince(0, 5 * 60, self::LABELS),
            Time::elapsedSince(5 * 60, 0, self::LABELS),
        );
    }

    public function test_elapsed_short_unit_omits_plural_suffix(): void
    {
        // In short mode the labels are abbreviations and the
        // legacy never appends a plural suffix to them. Pinned
        // because a future refactor that "fixes" pluralisation
        // to apply in short mode would change every torrent /
        // user listing across the site.
        $this->assertSame('5m', Time::elapsedSince(0, 5 * 60, self::LABELS, shortUnit: true));
        $this->assertSame('2h&nbsp;30m', Time::elapsedSince(0, 2 * 3600 + 30 * 60, self::LABELS, shortUnit: true));
    }

    public function test_elapsed_short_unit_sub_minute_omits_plural(): void
    {
        // The "< 1{label}" branch also never appends a plural suffix.
        $this->assertSame('&lt; 1m', Time::elapsedSince(0, 30, self::LABELS, shortUnit: true));
    }

    public function test_elapsed_handles_missing_labels_gracefully(): void
    {
        // If a translation is missing, the helper must not blow
        // up — it just emits the number with an empty label. The
        // legacy `?? ''` coercion is preserved by the proxy.
        $sparse = ['plural_suffix' => ''];
        $this->assertSame('5', Time::elapsedSince(0, 5 * 60, $sparse));
        $this->assertSame('&lt; 1', Time::elapsedSince(0, 0, $sparse));
    }
}
