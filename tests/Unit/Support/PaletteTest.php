<?php

namespace Tests\Unit\Support;

use App\Support\Palette;
use PHPUnit\Framework\TestCase;

final class PaletteTest extends TestCase
{
    // ---------- forumHighlight ----------

    public function test_forum_highlight_zero_returns_false(): void
    {
        // Legacy `get_hl_color(0)` returns false — `public/forums.php`
        // uses `$color == 0 || get_hl_color($color)` to gate the UI.
        $this->assertFalse(Palette::forumHighlight(0));
    }

    public function test_forum_highlight_default_argument_returns_false(): void
    {
        $this->assertFalse(Palette::forumHighlight());
    }

    public function test_forum_highlight_first_palette_index(): void
    {
        $this->assertSame('Black', Palette::forumHighlight(1));
    }

    public function test_forum_highlight_last_palette_index(): void
    {
        $this->assertSame('White', Palette::forumHighlight(40));
    }

    public function test_forum_highlight_out_of_range_returns_false(): void
    {
        // `default: return false` in the legacy switch — preserved.
        $this->assertFalse(Palette::forumHighlight(41));
        $this->assertFalse(Palette::forumHighlight(99));
        $this->assertFalse(Palette::forumHighlight(-1));
    }

    public function test_forum_highlight_full_palette_mid_range(): void
    {
        $expected = [
            2 => 'Sienna',
            3 => 'DarkOliveGreen',
            4 => 'DarkGreen',
            5 => 'DarkSlateBlue',
            6 => 'Navy',
            7 => 'Indigo',
            8 => 'DarkSlateGray',
            9 => 'DarkRed',
            10 => 'DarkOrange',
            11 => 'Olive',
            12 => 'Green',
            13 => 'Teal',
            14 => 'Blue',
            15 => 'SlateGray',
            16 => 'DimGray',
            17 => 'Red',
            18 => 'SandyBrown',
            19 => 'YellowGreen',
            20 => 'SeaGreen',
            21 => 'MediumTurquoise',
            22 => 'RoyalBlue',
            23 => 'Purple',
            24 => 'Gray',
            25 => 'Magenta',
            26 => 'Orange',
            27 => 'Yellow',
            28 => 'Lime',
            29 => 'Cyan',
            30 => 'DeepSkyBlue',
            31 => 'DarkOrchid',
            32 => 'Silver',
            33 => 'Pink',
            34 => 'Wheat',
            35 => 'LemonChiffon',
            36 => 'PaleGreen',
            37 => 'PaleTurquoise',
            38 => 'LightBlue',
            39 => 'Plum',
        ];
        foreach ($expected as $index => $name) {
            $this->assertSame($name, Palette::forumHighlight($index), "index $index should map to $name");
        }
    }

    // ---------- seederLink ----------

    public function test_seeder_link_zero_returns_red(): void
    {
        // Legacy `linkcolor(0)` returns "red" — matches no-seeders branch.
        $this->assertSame('red', Palette::seederLink(0));
    }

    public function test_seeder_link_positive_returns_green(): void
    {
        $this->assertSame('green', Palette::seederLink(1));
        $this->assertSame('green', Palette::seederLink(2));
        $this->assertSame('green', Palette::seederLink(99));
    }

    public function test_seeder_link_falsy_string_returns_red(): void
    {
        // Legacy contract: `!$num` truthy-check, so `"0"` and `""` are red.
        $this->assertSame('red', Palette::seederLink(''));
        $this->assertSame('red', Palette::seederLink('0'));
    }

    public function test_seeder_link_null_returns_red(): void
    {
        $this->assertSame('red', Palette::seederLink(null));
    }

    public function test_seeder_link_non_zero_string_returns_green(): void
    {
        // `"5"` evaluates truthy → green.
        $this->assertSame('green', Palette::seederLink('5'));
    }

    public function test_seeder_link_negative_returns_green_legacy_quirk(): void
    {
        // `!(-1)` is false → green. Negative seeder counts can never
        // appear in production but the legacy contract is preserved.
        $this->assertSame('green', Palette::seederLink(-1));
    }
}
