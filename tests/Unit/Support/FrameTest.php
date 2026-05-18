<?php

namespace Tests\Unit\Support;

use App\Support\Frame;
use PHPUnit\Framework\TestCase;

final class FrameTest extends TestCase
{
    private const CONTENT_WIDTH = 1200;

    // ---------- mainOpen ----------

    public function test_main_open_default_emits_full_width_centered_off(): void
    {
        // Legacy quirk: when `$center` is false, the emitted <td>
        // is `class="embedded" >` (trailing space) — preserved verbatim.
        $expected = '<table class="main" width="1200" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded" >';
        $this->assertSame($expected, Frame::mainOpen('', false, 100, self::CONTENT_WIDTH));
    }

    public function test_main_open_with_caption_prepends_h2(): void
    {
        $expected = '<h2>Title</h2>'
            .'<table class="main" width="1200" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded" >';
        $this->assertSame($expected, Frame::mainOpen('Title', false, 100, self::CONTENT_WIDTH));
    }

    public function test_main_open_centered_emits_double_space_quirk(): void
    {
        // Legacy quirk: when `$center === true`, the emitted <td>
        // becomes `class="embedded"  align="center">` — two spaces
        // before `align` (one from the format string, one from the
        // leading space inside `$tdextra`).
        $expected = '<table class="main" width="1200" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded"  align="center">';
        $this->assertSame($expected, Frame::mainOpen('', true, 100, self::CONTENT_WIDTH));
    }

    public function test_main_open_percentage_string_used_verbatim(): void
    {
        $expected = '<table class="main" width="50%" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded" >';
        $this->assertSame($expected, Frame::mainOpen('', false, '50%', self::CONTENT_WIDTH));
    }

    public function test_main_open_numeric_width_scales_to_content_width(): void
    {
        // 50 % of CONTENT_WIDTH (1200) = 600 px.
        $expected = '<table class="main" width="600" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded" >';
        $this->assertSame($expected, Frame::mainOpen('', false, 50, self::CONTENT_WIDTH));
    }

    public function test_main_open_numeric_string_width_also_scales(): void
    {
        $expected = '<table class="main" width="240" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded" >';
        $this->assertSame($expected, Frame::mainOpen('', false, '20', self::CONTENT_WIDTH));
    }

    public function test_main_open_caption_and_center_combined(): void
    {
        $expected = '<h2>Hi</h2>'
            .'<table class="main" width="1200" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded"  align="center">';
        $this->assertSame($expected, Frame::mainOpen('Hi', true, 100, self::CONTENT_WIDTH));
    }

    // ---------- open / close ----------

    public function test_open_default_left_aligned_caption(): void
    {
        $expected = '<h2 align="left">Foo</h2>'
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10">'
            ."<tr><td class=\"text\" >\n";
        $this->assertSame($expected, Frame::open('Foo', false, 10, '100%', 'left'));
    }

    public function test_open_centered_emits_double_space_quirk(): void
    {
        // Same double-space quirk as `mainOpen()`: format string has a
        // trailing space before `$tdextra`, and `$tdextra` itself starts
        // with ` align=...`. Preserved bit-for-bit.
        $expected = '<h2 align="center">Foo</h2>'
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10">'
            ."<tr><td class=\"text\"  align=\"center\">\n";
        $this->assertSame($expected, Frame::open('Foo', true, 10, '100%', 'center'));
    }

    public function test_open_without_caption_omits_h2(): void
    {
        $expected = '<table width="80%" border="1" cellspacing="0" cellpadding="5">'
            ."<tr><td class=\"text\" >\n";
        $this->assertSame($expected, Frame::open('', false, 5, '80%', 'left'));
    }

    public function test_close_constant_is_legacy_payload(): void
    {
        // Both `end_main_frame()` and `end_frame()` emit identically.
        $this->assertSame("</td></tr></table>\n", Frame::CLOSE);
    }

    // ---------- tableOpen / TABLE_CLOSE ----------

    public function test_table_open_defaults_no_extra_width(): void
    {
        $expected = '<table class="main" border="1" cellspacing="0" cellpadding="5">';
        $this->assertSame($expected, Frame::tableOpen(false, 5));
    }

    public function test_table_open_fullwidth_emits_legacy_bug_inside_class(): void
    {
        // Legacy bug preserved: `class="main".$width` concatenates the
        // " width=50%" fragment INSIDE the class attribute, producing
        // `class="main width=50%"`. The browser treats `width=50%` as a
        // class name, not an attribute. Existing call sites have rendered
        // this invalid markup for years; we keep it bit-for-bit.
        $expected = '<table class="main width=50%" border="1" cellspacing="0" cellpadding="5">';
        $this->assertSame($expected, Frame::tableOpen(true, 5));
    }

    public function test_table_open_padding_passes_through(): void
    {
        $expected = '<table class="main" border="1" cellspacing="0" cellpadding="20">';
        $this->assertSame($expected, Frame::tableOpen(false, 20));
    }

    public function test_table_close_constant_is_legacy_payload(): void
    {
        $this->assertSame("</table>\n", Frame::TABLE_CLOSE);
    }

    // ---------- stdMessage ----------

    public function test_std_message_with_heading_emits_h2_and_text(): void
    {
        $expected = '<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0">'
            ."<tr><td class=\"embedded\">\n"
            ."<h2>Heading</h2>\n"
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            ."Body</td></tr></table></td></tr></table>\n";
        $this->assertSame($expected, Frame::stdMessage('Heading', 'Body', false));
    }

    public function test_std_message_empty_heading_omits_h2(): void
    {
        $expected = '<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0">'
            ."<tr><td class=\"embedded\">\n"
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            ."Body</td></tr></table></td></tr></table>\n";
        $this->assertSame($expected, Frame::stdMessage('', 'Body', false));
    }

    public function test_std_message_zero_heading_is_treated_as_empty_legacy_quirk(): void
    {
        // Legacy quirk: original `if ($heading)` is a bool-check, so a
        // literal `'0'` heading is treated as empty and the <h2> is
        // suppressed. Preserved verbatim.
        $expected = '<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0">'
            ."<tr><td class=\"embedded\">\n"
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            ."Body</td></tr></table></td></tr></table>\n";
        $this->assertSame($expected, Frame::stdMessage('0', 'Body', false));
    }

    public function test_std_message_htmlstrip_trims_and_escapes_both_fields(): void
    {
        // Both heading and text trimmed + htmlspecialchars'd in lockstep.
        $expected = '<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0">'
            ."<tr><td class=\"embedded\">\n"
            ."<h2>&lt;b&gt;Hi&lt;/b&gt;</h2>\n"
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            ."Tom &amp; Jerry &quot;evil&quot;</td></tr></table></td></tr></table>\n";
        $this->assertSame(
            $expected,
            Frame::stdMessage('  <b>Hi</b>  ', "\nTom & Jerry \"evil\"\t", true)
        );
    }

    public function test_std_message_htmlstrip_off_does_not_escape(): void
    {
        $expected = '<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0">'
            ."<tr><td class=\"embedded\">\n"
            ."<h2><b>Hi</b></h2>\n"
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            .'<p>raw</p></td></tr></table></td></tr></table>'."\n";
        $this->assertSame(
            $expected,
            Frame::stdMessage('<b>Hi</b>', '<p>raw</p>', false)
        );
    }

    // ---------- sqlError ----------

    public function test_sql_error_with_file_and_line_emits_location(): void
    {
        $expected = '<table border="0" bgcolor="blue" align="left" cellspacing="0" cellpadding="10" style="background: blue;">'
            ."<tr><td class=\"embedded\"><font color=\"white\"><h1>SQL Error</h1>\n"
            .'<b>Boom!<p>in /tmp/x.php, line 42</p></b></font></td></tr></table>';
        $this->assertSame($expected, Frame::sqlError('Boom!', '/tmp/x.php', '42'));
    }

    public function test_sql_error_without_file_omits_location(): void
    {
        $expected = '<table border="0" bgcolor="blue" align="left" cellspacing="0" cellpadding="10" style="background: blue;">'
            ."<tr><td class=\"embedded\"><font color=\"white\"><h1>SQL Error</h1>\n"
            .'<b>Boom!</b></font></td></tr></table>';
        $this->assertSame($expected, Frame::sqlError('Boom!', '', ''));
    }

    public function test_sql_error_zero_file_treated_as_empty_legacy_quirk(): void
    {
        // Legacy quirk: `$file != ''` is a loose comparison so the
        // string `'0'` collapses to empty. Bool-check after string
        // cast reproduces that exactly.
        $expected = '<table border="0" bgcolor="blue" align="left" cellspacing="0" cellpadding="10" style="background: blue;">'
            ."<tr><td class=\"embedded\"><font color=\"white\"><h1>SQL Error</h1>\n"
            .'<b>Boom!</b></font></td></tr></table>';
        $this->assertSame($expected, Frame::sqlError('Boom!', '0', '42'));
        $this->assertSame($expected, Frame::sqlError('Boom!', '/tmp/x.php', '0'));
    }

    public function test_sql_error_does_not_escape_the_error_message(): void
    {
        // The legacy proxy passes the raw SQL error through; downstream
        // pages have been rendering HTML-bearing strings as-is for years.
        $expected = '<table border="0" bgcolor="blue" align="left" cellspacing="0" cellpadding="10" style="background: blue;">'
            ."<tr><td class=\"embedded\"><font color=\"white\"><h1>SQL Error</h1>\n"
            .'<b>"<script>"</b></font></td></tr></table>';
        $this->assertSame($expected, Frame::sqlError('"<script>"', '', ''));
    }
}
