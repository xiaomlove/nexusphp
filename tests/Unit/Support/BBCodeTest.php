<?php

namespace Tests\Unit\Support;

use App\Support\BBCode;
use Tests\TestCase;

class BBCodeTest extends TestCase
{
    // ---------- url ----------

    public function test_url_with_empty_text_falls_back_to_url(): void
    {
        // Pinned legacy contract: when no text is supplied, the
        // anchor text equals the URL. Single source of truth for
        // the `[url]https://…[/url]` BBCode shape.
        $this->assertSame(
            '<a href="https://example.com">https://example.com</a>',
            BBCode::url('https://example.com'),
        );
    }

    public function test_url_target_blank_only_when_new_window_true(): void
    {
        // Legacy `formatUrl()` uses `$newWindow == true` (loose) to
        // gate the `target="_blank"`. We pin the explicit-bool
        // contract; the legacy proxy is responsible for the loose
        // coercion at the call site.
        $this->assertStringNotContainsString('target="_blank"', BBCode::url('u', false));
        $this->assertStringContainsString('target="_blank"', BBCode::url('u', true));
    }

    public function test_url_class_attribute_is_emitted_when_non_empty(): void
    {
        $this->assertStringContainsString('class="faqlink"', BBCode::url('u', true, 't', 'faqlink'));
        $this->assertStringNotContainsString('class=', BBCode::url('u', true, 't', ''));
    }

    public function test_url_text_zero_string_is_treated_as_empty(): void
    {
        // Pinned legacy quirk: `if (!$text)` is loose, so the
        // string `'0'` is treated as no-text and falls back to the
        // URL. Anyone trying to render a literal "0" as link text
        // gets the URL instead — preserved verbatim because the
        // BBCode parser regex `(.+?)` rarely captures bare "0".
        $this->assertSame(
            '<a href="https://example.com">https://example.com</a>',
            BBCode::url('https://example.com', false, '0'),
        );
    }

    public function test_url_attribute_order_is_class_then_href_then_target(): void
    {
        // Pin the exact attribute order to keep diffs visible if
        // anyone ever rewrites this helper. The legacy contract is:
        //   <a CLASS href=URL TARGET>TEXT</a>
        $this->assertSame(
            '<a class="cls" href="u" target="_blank">t</a>',
            BBCode::url('u', true, 't', 'cls'),
        );
    }

    // ---------- adUrl ----------

    public function test_ad_url_wraps_destination_in_adredir(): void
    {
        // Pinned legacy quirk: the `&amp;` separator is HTML-encoded
        // inline (NOT raw `&`). Removing it would double-encode
        // when the surrounding pipeline runs htmlspecialchars().
        $html = BBCode::adUrl(42, 'https://example.com/foo?x=1', 'Click');
        $this->assertStringContainsString('adredir.php?id=42&amp;url=https%3A%2F%2Fexample.com%2Ffoo%3Fx%3D1', $html);
        $this->assertStringContainsString('>Click</a>', $html);
        // Default $newWindow is TRUE for adUrl (unlike plain url).
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function test_ad_url_passes_through_url_helper(): void
    {
        // adUrl is documented as delegating to url() — pin that
        // the output really does match url() with the same args.
        $manual = BBCode::url('adredir.php?id=7&amp;url='.rawurlencode('https://x.test'), true, 'go');
        $this->assertSame($manual, BBCode::adUrl(7, 'https://x.test', 'go'));
    }

    // ---------- code ----------

    public function test_code_wraps_text_in_codetop_codemain(): void
    {
        // Pinned legacy contract: leading `<br />`, then a
        // `codetop` div with the label, then `codemain` with the
        // text inside `<pre><code>…</code></pre>`, then a trailing
        // `<br />`. The label is passed in from the proxy.
        $this->assertSame(
            '<br /><div class="codetop">Code</div><div class="codemain"><pre><code>echo "hi";</code></pre></div><br />',
            BBCode::code('echo "hi";', 'Code'),
        );
    }

    public function test_code_does_not_escape_text(): void
    {
        // The BBCode parser runs htmlspecialchars on the whole post
        // BEFORE this helper is reached, so the helper must NOT
        // re-escape. Pin that `<` survives untouched.
        $this->assertStringContainsString('<not-escaped>', BBCode::code('<not-escaped>', 'L'));
    }

    // ---------- img ----------

    public function test_img_empty_src_returns_empty_string(): void
    {
        $this->assertSame('', BBCode::img('', true, 800, 600));
        $this->assertSame('', BBCode::img('0', true, 800, 600));
    }

    public function test_img_without_resizer_emits_plain_img(): void
    {
        $html = BBCode::img('pic.png', false, 800, 600, 'myid');
        $this->assertStringContainsString('src="pic.png"', $html);
        $this->assertStringContainsString('id="myid"', $html);
        $this->assertStringContainsString("onerror=\"handleImageError(this, 'pic.png');\"", $html);
        $this->assertStringNotContainsString('onload=', $html);
        $this->assertStringNotContainsString('data-zoomable', $html);
    }

    public function test_img_with_resizer_emits_scale_and_zoomable(): void
    {
        // Pinned legacy contract: the resizer attaches the `Scale()`
        // JS hook on `onload` AND tags the element with
        // `data-zoomable` for the lightbox script. There is a
        // deliberate trailing space inside `data-zoomable ` which
        // results in TWO consecutive spaces before `onerror`. We
        // pin the two-space variant exactly.
        $html = BBCode::img('pic.png', true, 800, 600);
        $this->assertStringContainsString('onload="Scale(this, 800, 600);"', $html);
        $this->assertStringContainsString('data-zoomable ', $html);
        $this->assertStringContainsString('data-zoomable  onerror=', $html);
    }

    public function test_img_id_attribute_defaults_to_empty(): void
    {
        $this->assertStringContainsString('id=""', BBCode::img('pic.png', false, 1, 1));
    }

    // ---------- flash ----------

    public function test_flash_default_dimensions_are_500_by_300(): void
    {
        $html = BBCode::flash('f.swf');
        $this->assertStringContainsString('width="500"', $html);
        $this->assertStringContainsString('height="300"', $html);
        $this->assertStringContainsString('value="f.swf"', $html);
        $this->assertStringContainsString('src="f.swf"', $html);
        $this->assertStringContainsString('application/x-shockwave-flash', $html);
    }

    public function test_flash_explicit_dimensions_are_honoured(): void
    {
        $html = BBCode::flash('f.swf', 100, 200);
        $this->assertStringContainsString('width="100"', $html);
        $this->assertStringContainsString('height="200"', $html);
    }

    public function test_flash_empty_src_returns_empty_string(): void
    {
        $this->assertSame('', BBCode::flash(''));
    }

    public function test_flash_zero_dimension_falls_back_to_default(): void
    {
        // Pinned legacy quirk: `if (!$width)` is loose, so 0,
        // '0', '', null all fall through to the default. We
        // accept int|string and rely on that contract.
        $html = BBCode::flash('f.swf', 0, '0');
        $this->assertStringContainsString('width="500"', $html);
        $this->assertStringContainsString('height="300"', $html);
    }

    // ---------- flv ----------

    public function test_flv_default_dimensions_are_320_by_240(): void
    {
        $html = BBCode::flv('v.flv');
        $this->assertStringContainsString('width="320"', $html);
        $this->assertStringContainsString('height="240"', $html);
        $this->assertStringContainsString('flvplayer.swf?file=v.flv', $html);
        $this->assertStringContainsString('allowfullscreen="true"', $html);
    }

    public function test_flv_empty_src_returns_empty_string(): void
    {
        $this->assertSame('', BBCode::flv(''));
    }

    // ---------- youtube ----------

    public function test_youtube_default_dimensions_are_560_by_315(): void
    {
        $html = BBCode::youtube('https://www.youtube.com/watch?v=ABCdef');
        $this->assertStringContainsString('width="560"', $html);
        $this->assertStringContainsString('height="315"', $html);
        $this->assertStringContainsString('src="https://www.youtube.com/embed/ABCdef"', $html);
    }

    public function test_youtube_extracts_v_parameter_from_querystring(): void
    {
        $html = BBCode::youtube('https://www.youtube.com/watch?ab_channel=Foo&v=XYZ', 100, 200);
        $this->assertStringContainsString('embed/XYZ', $html);
        $this->assertStringContainsString('width="100"', $html);
        $this->assertStringContainsString('height="200"', $html);
    }

    public function test_youtube_with_no_v_parameter_emits_broken_embed_url(): void
    {
        // Pinned legacy quirk: the helper does NOT validate the
        // URL; if there's no `v=` query param the iframe src ends
        // with `embed/` (empty video id). Preserved verbatim
        // because the BBCode parser rarely lets this through.
        $html = BBCode::youtube('https://example.com/foo');
        $this->assertStringContainsString('src="https://www.youtube.com/embed/"', $html);
    }

    public function test_youtube_empty_src_returns_empty_string(): void
    {
        $this->assertSame('', BBCode::youtube(''));
    }

    // ---------- video ----------

    public function test_video_default_dimensions_are_560_by_315(): void
    {
        $html = BBCode::video('https://example.com/v.mp4');
        $this->assertSame(
            '<video controls width="560" height="315"><source src="https://example.com/v.mp4" /><a href="https://example.com/v.mp4">https://example.com/v.mp4</a></video>',
            $html,
        );
    }

    public function test_video_empty_src_returns_empty_string(): void
    {
        $this->assertSame('', BBCode::video(''));
    }

    // ---------- audio ----------

    public function test_audio_emits_html5_element_with_fallback_link(): void
    {
        $this->assertSame(
            '<audio controls><source src="https://example.com/a.mp3" /><a href="https://example.com/a.mp3">https://example.com/a.mp3</a></audio>',
            BBCode::audio('https://example.com/a.mp3'),
        );
    }

    public function test_audio_empty_src_returns_empty_string(): void
    {
        $this->assertSame('', BBCode::audio(''));
    }

    // ---------- spoiler ----------

    public function test_spoiler_uses_supplied_title_when_non_empty(): void
    {
        $this->assertSame(
            '<details><summary>My Title</summary>body</details>',
            BBCode::spoiler('body', 'My Title', 'Default'),
        );
    }

    public function test_spoiler_falls_back_to_default_title_when_empty(): void
    {
        // Pinned legacy contract: `if (!$title)` is loose, so the
        // default kicks in for '', '0', null. The legacy proxy
        // threads `$lang_functions['spoiler_default_title']`
        // through the `$defaultTitle` parameter.
        $this->assertSame(
            '<details><summary>Default</summary>body</details>',
            BBCode::spoiler('body', '', 'Default'),
        );
        $this->assertSame(
            '<details><summary>Default</summary>body</details>',
            BBCode::spoiler('body', '0', 'Default'),
        );
    }

    public function test_spoiler_default_collapsed_omits_open_attribute(): void
    {
        $html = BBCode::spoiler('body', 'Title', 'Default', true);
        $this->assertStringNotContainsString(' open', $html);
        $this->assertStringStartsWith('<details>', $html);
    }

    public function test_spoiler_non_collapsed_adds_open_attribute(): void
    {
        $html = BBCode::spoiler('body', 'Title', 'Default', false);
        $this->assertStringStartsWith('<details open>', $html);
    }

    // ---------- hidden ----------

    public function test_hidden_wraps_content_in_hidden_text_span(): void
    {
        // Delegates to Strings::hidden(); pin the visible output.
        $this->assertSame('<span class="hidden-text">secret</span>', BBCode::hidden('secret'));
    }

    // ---------- textAlign ----------

    public function test_text_align_wraps_text_in_styled_div(): void
    {
        $this->assertSame(
            '<div style="text-align: center">hello</div>',
            BBCode::textAlign('hello', 'center'),
        );
    }

    public function test_text_align_emits_align_value_verbatim(): void
    {
        // Pinned legacy contract: the helper does NOT validate the
        // alignment value. The BBCode parser only ever calls it
        // with `left`, `center`, `right`, `justify` — but if a
        // future caller passes garbage, it lands directly in the
        // `style` attribute.
        $html = BBCode::textAlign('x', 'right; color: red');
        $this->assertStringContainsString('style="text-align: right; color: red"', $html);
    }

    // ---------- quotes ----------

    public function test_quotes_rewrites_simple_quote_block(): void
    {
        $this->assertSame(
            '<fieldset><legend> Quote </legend><br />hello</fieldset><br />',
            BBCode::quotes('[quote]hello[/quote]', 'Quote'),
        );
    }

    public function test_quotes_rewrites_quote_with_author(): void
    {
        $this->assertSame(
            '<fieldset><legend> Quote: alice </legend><br />hi</fieldset><br />',
            BBCode::quotes('[quote=alice]hi[/quote]', 'Quote'),
        );
    }

    public function test_quotes_returns_input_verbatim_on_tag_count_mismatch(): void
    {
        // Pinned legacy contract: unbalanced [quote]/[/quote] means we
        // hand back the raw input rather than emitting half-open HTML.
        $this->assertSame('[quote]oops', BBCode::quotes('[quote]oops', 'Quote'));
        $this->assertSame('oops[/quote]', BBCode::quotes('oops[/quote]', 'Quote'));
    }

    public function test_quotes_returns_input_verbatim_when_close_precedes_open(): void
    {
        // Even with matching counts, if any [/quote] sits before its
        // paired [quote] by index, legacy bails out and returns raw
        // input rather than emitting broken HTML.
        $input = '[/quote]something[quote]other[/quote]';
        $this->assertSame($input, BBCode::quotes($input, 'Quote'));
    }

    public function test_quotes_handles_nested_blocks_in_legacy_order(): void
    {
        // Pinned: as long as each opening tag's position is <= the
        // matching closing tag's position by index, the rewrite fires.
        $input = '[quote=a][quote=b]x[/quote][/quote]';
        $output = BBCode::quotes($input, 'Quote');
        $this->assertStringContainsString('<fieldset><legend> Quote: a </legend>', $output);
        $this->assertStringContainsString('<fieldset><legend> Quote: b </legend>', $output);
        $this->assertSame(
            2,
            substr_count($output, '</fieldset><br />'),
        );
    }

    public function test_quotes_is_case_insensitive(): void
    {
        $this->assertStringContainsString(
            '<fieldset><legend> Quote </legend>',
            BBCode::quotes('[QUOTE]x[/QUOTE]', 'Quote'),
        );
    }

    public function test_quotes_passes_through_text_with_no_quote_tags(): void
    {
        $this->assertSame('plain text', BBCode::quotes('plain text', 'Quote'));
    }

    // ---------- stripAll ----------

    public function test_strip_all_removes_parameterless_bbcode_tags(): void
    {
        $this->assertSame(
            'bold italic',
            BBCode::stripAll('[b]bold[/b] [i]italic[/i]', []),
        );
    }

    public function test_strip_all_removes_parametered_bbcode_tags_via_regex(): void
    {
        $this->assertSame(
            'link colour size font',
            BBCode::stripAll('[url=http://x]link[/url] [color=red]colour[/color] [size=12]size[/size] [font=arial]font[/font]', []),
        );
    }

    public function test_strip_all_resolves_known_emoji_to_replacement(): void
    {
        $this->assertSame(
            'hello :)',
            BBCode::stripAll('hello [em1]', [1 => ':)']),
        );
    }

    public function test_strip_all_drops_unknown_emoji(): void
    {
        $this->assertSame(
            'hi',
            BBCode::stripAll('hi[em42]', []),
        );
    }

    public function test_strip_all_strips_remaining_html_tags(): void
    {
        $this->assertSame(
            'bold',
            BBCode::stripAll('<b>bold</b>', []),
        );
    }

    public function test_strip_all_trims_surrounding_whitespace(): void
    {
        $this->assertSame(
            'core',
            BBCode::stripAll("\n\n  [b]core[/b]  \t", []),
        );
    }

    public function test_strip_all_removes_youtube_and_spoiler_with_params(): void
    {
        $this->assertSame(
            'video spoiler',
            BBCode::stripAll('[youtube 100x100]video[/youtube] [spoiler=title]spoiler[/spoiler]', []),
        );
    }

    public function test_strip_all_returns_empty_for_pure_bbcode_input(): void
    {
        $this->assertSame('', BBCode::stripAll('[b][/b]', []));
    }
}
