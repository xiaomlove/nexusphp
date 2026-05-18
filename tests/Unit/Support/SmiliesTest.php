<?php

namespace Tests\Unit\Support;

use App\Support\Smilies;
use PHPUnit\Framework\TestCase;

final class SmiliesTest extends TestCase
{
    // ---------- link ----------

    public function test_link_emits_legacy_anchor_with_double_space_before_onmouseover(): void
    {
        // Legacy quirk: the format string concatenates `"\")\"  onmouseover=..."`
        // with two spaces between the closing quote of `href` and `onmouseover`.
        // Preserved bit-for-bit.
        $escaped = '&lt;table&gt;&lt;tr&gt;&lt;td&gt;&lt;img src=&#039;pic/smilies/4.gif&#039; alt=&#039;&#039; /&gt;&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;';
        $expected = '<a href="javascript: SmileIT(\'[em4]\',\'myform\',\'myta\')"  '
            .'onmouseover="domTT_activate(this, event, \'content\', \''.$escaped.'\', '
            .'\'trail\', false, \'delay\', 0,\'lifetime\',10000,\'styleClass\',\'smilies\',\'maxWidth\', 400);">'
            .'<img style="max-width: 25px;" src="pic/smilies/4.gif" alt="" /></a>';
        $this->assertSame($expected, Smilies::link('myform', 'myta', 4));
    }

    public function test_link_does_not_escape_formname_or_taname_legacy_quirk(): void
    {
        // Legacy quirk: `$formname` and `$taname` are concatenated raw —
        // single quotes inside them break out of the JS string. Preserved
        // verbatim so existing pages with safe form/ta names keep working.
        $result = Smilies::link("o'malley", "ta'name", 1);
        $this->assertStringContainsString("SmileIT('[em1]','o'malley','ta'name')", $result);
    }

    public function test_link_uses_smily_number_in_three_places(): void
    {
        $result = Smilies::link('f', 't', 42);
        $this->assertStringContainsString('[em42]', $result);
        $this->assertStringContainsString('pic/smilies/42.gif', $result);
        // Two `pic/smilies/N.gif` occurrences — one in the tooltip body
        // (HTML-escaped) and one in the visible <img src>.
        $this->assertSame(2, substr_count($result, 'pic/smilies/42.gif'));
    }

    // ---------- quickRow ----------

    public function test_quick_row_wraps_in_centered_div(): void
    {
        $result = Smilies::quickRow('myform', 'myta');
        $this->assertStringStartsWith('<div align="center">', $result);
        $this->assertStringEndsWith('</div>', $result);
    }

    public function test_quick_row_emits_seventeen_links_in_legacy_order(): void
    {
        // Pinned order from the original `smile_row()` array literal —
        // existing pages depend on this exact sequence.
        $expectedNumbers = [4, 5, 39, 25, 11, 8, 10, 15, 27, 57, 42, 122, 52, 28, 29, 30, 176];
        $result = Smilies::quickRow('f', 't');

        $matches = [];
        preg_match_all('/SmileIT\(\'\[em(\d+)\]\'/', $result, $matches);
        $this->assertSame(array_map('strval', $expectedNumbers), $matches[1]);
    }

    public function test_quick_row_passes_form_and_taname_to_each_link(): void
    {
        $result = Smilies::quickRow('formX', 'taX');
        // 17 links × 1 SmileIT call each = 17 occurrences of the
        // `'formX','taX'` pair.
        $this->assertSame(17, substr_count($result, "'formX','taX'"));
    }

    // ---------- framedTable ----------

    public function test_framed_table_opens_with_centered_frame_and_table(): void
    {
        $result = Smilies::framedTable('Smilies', 'Type', 'Insert');
        // Frame::open(title, center=true, padding=10, width=100%, caption=left)
        $this->assertStringContainsString('<h2 align="left">Smilies</h2>', $result);
        $this->assertStringContainsString('<table width="100%" border="1" cellspacing="0" cellpadding="10">', $result);
        $this->assertStringContainsString("<tr><td class=\"text\"  align=\"center\">\n", $result);
        // Frame::tableOpen(false, 5)
        $this->assertStringContainsString('<table class="main" border="1" cellspacing="0" cellpadding="5">', $result);
    }

    public function test_framed_table_emits_header_row_with_provided_labels(): void
    {
        $result = Smilies::framedTable('S', 'Foo', 'Bar');
        $this->assertStringContainsString(
            '<tr><td class="colhead">Foo</td><td class="colhead">Bar</td></tr>'."\n",
            $result
        );
    }

    public function test_framed_table_emits_191_smiley_rows(): void
    {
        $result = Smilies::framedTable('S', 'A', 'B');
        // Loop runs `for ($i = 1; $i < 192; $i++)` → 191 rows.
        for ($i = 1; $i < 192; $i++) {
            $this->assertStringContainsString(
                '<tr><td>[em'.$i.']</td><td><img src="pic/smilies/'.$i.'.gif" alt="[em'.$i.']" /></td></tr>'."\n",
                $result,
                "Expected smiley row $i in framedTable output"
            );
        }
        // Sanity check: no [em192] row.
        $this->assertStringNotContainsString('[em192]', $result);
    }

    public function test_framed_table_closes_table_then_frame(): void
    {
        $result = Smilies::framedTable('S', 'A', 'B');
        // Frame::TABLE_CLOSE (</table>\n) then Frame::CLOSE (</td></tr></table>\n).
        $this->assertStringEndsWith("</table>\n</td></tr></table>\n", $result);
    }
}
