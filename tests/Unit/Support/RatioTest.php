<?php

namespace Tests\Unit\Support;

use App\Support\Ratio;
use PHPUnit\Framework\TestCase;

class RatioTest extends TestCase
{
    public function test_share_returns_dash_when_both_zero(): void
    {
        $this->assertSame('---', Ratio::share(0, 0));
        $this->assertSame('---', Ratio::share(0.0, 0.0));
    }

    public function test_share_returns_infinity_when_downloaded_is_zero_but_uploaded_is_nonzero(): void
    {
        $this->assertSame('Infinity', Ratio::share(1, 0));
        $this->assertSame('Infinity', Ratio::share(1024 * 1024, 0));
    }

    public function test_share_returns_truncated_three_decimal_float(): void
    {
        // Legacy uses floor(... * 1000) / 1000 — that's truncation,
        // NOT rounding. A 1.2349 ratio must come back as 1.234 (not 1.235).
        $this->assertSame(0.5, Ratio::share(500, 1000));
        $this->assertSame(1.0, Ratio::share(1000, 1000));
        $this->assertSame(2.5, Ratio::share(2500, 1000));
        $this->assertSame(0.333, Ratio::share(1, 3));
        $this->assertSame(0.666, Ratio::share(2, 3));
    }

    public function test_share_floor_truncation_does_not_round_up(): void
    {
        // 1.23499 truncates to 1.234 — pin this against a "smart"
        // refactor that swaps floor() for round().
        $uploaded = 12349;
        $downloaded = 10000;
        $this->assertSame(1.234, Ratio::share($uploaded, $downloaded));
    }

    public function test_color_red_for_low_ratios(): void
    {
        $this->assertSame('#ff0000', Ratio::color(0));
        $this->assertSame('#ff0000', Ratio::color(0.05));
        $this->assertSame('#ee0000', Ratio::color(0.15));
    }

    public function test_color_bucket_boundaries(): void
    {
        // Strict `<` comparisons — the *exact* boundary lands in
        // the NEXT bucket. 0.1 is not < 0.1, so it falls through
        // to the < 0.2 bucket.
        $this->assertSame('#ee0000', Ratio::color(0.1));
        $this->assertSame('#dd0000', Ratio::color(0.2));
        $this->assertSame('#cc0000', Ratio::color(0.3));
        $this->assertSame('#bb0000', Ratio::color(0.4));
        $this->assertSame('#aa0000', Ratio::color(0.5));
        $this->assertSame('#990000', Ratio::color(0.6));
        $this->assertSame('#880000', Ratio::color(0.7));
        $this->assertSame('#770000', Ratio::color(0.8));
        $this->assertSame('#660000', Ratio::color(0.9));
    }

    public function test_color_empty_for_healthy_ratios(): void
    {
        $this->assertSame('', Ratio::color(1));
        $this->assertSame('', Ratio::color(1.5));
        $this->assertSame('', Ratio::color(100));
    }

    public function test_seed_leech_color_uses_narrower_buckets(): void
    {
        // Boundaries are 10× narrower than `color()`.
        $this->assertSame('#ff0000', Ratio::seedLeechColor(0));
        $this->assertSame('#ff0000', Ratio::seedLeechColor(0.024));
        $this->assertSame('#ee0000', Ratio::seedLeechColor(0.025));
        $this->assertSame('#660000', Ratio::seedLeechColor(0.24));
        $this->assertSame('#110000', Ratio::seedLeechColor(0.37));
        $this->assertSame('', Ratio::seedLeechColor(0.375));
        $this->assertSame('', Ratio::seedLeechColor(1));
    }

    public function test_image_picks_smiley_for_each_bucket(): void
    {
        // Verifies both the bucket boundaries (>=) and the
        // not-quite-monotonic smiley index ordering. The indexes
        // (163 / 117 / 5 / 3 / 2 / 34 / 10 / 52) reference real
        // files under `public/pic/smilies/*.gif`.
        $this->assertSame('<img src="pic/smilies/163.gif" alt="" />', Ratio::image(16));
        $this->assertSame('<img src="pic/smilies/163.gif" alt="" />', Ratio::image(32));
        $this->assertSame('<img src="pic/smilies/117.gif" alt="" />', Ratio::image(8));
        $this->assertSame('<img src="pic/smilies/117.gif" alt="" />', Ratio::image(15.999));
        $this->assertSame('<img src="pic/smilies/5.gif" alt="" />', Ratio::image(4));
        $this->assertSame('<img src="pic/smilies/3.gif" alt="" />', Ratio::image(2));
        $this->assertSame('<img src="pic/smilies/2.gif" alt="" />', Ratio::image(1));
        $this->assertSame('<img src="pic/smilies/34.gif" alt="" />', Ratio::image(0.5));
        $this->assertSame('<img src="pic/smilies/10.gif" alt="" />', Ratio::image(0.25));
        $this->assertSame('<img src="pic/smilies/52.gif" alt="" />', Ratio::image(0));
        $this->assertSame('<img src="pic/smilies/52.gif" alt="" />', Ratio::image(0.24));
    }
}
