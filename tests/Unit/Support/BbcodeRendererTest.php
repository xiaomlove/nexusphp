<?php

namespace Tests\Unit\Support;

use App\Support\BbcodeRenderer;
use PHPUnit\Framework\TestCase;

class BbcodeRendererTest extends TestCase
{
    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', BbcodeRenderer::toHtml(null));
        $this->assertSame('', BbcodeRenderer::toHtml(''));
    }

    public function test_plain_text_is_escaped_and_linebreaks_preserved(): void
    {
        $html = BbcodeRenderer::toHtml("hello <world>\nline two");
        $this->assertStringContainsString('hello &lt;world&gt;', $html);
        $this->assertStringContainsString('<br>', $html);
        $this->assertStringContainsString('line two', $html);
    }

    public function test_inline_emphasis_renders(): void
    {
        $html = BbcodeRenderer::toHtml('a [b]bold[/b] [i]italic[/i] [u]uline[/u] [s]strike[/s] z');
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<u>uline</u>', $html);
        $this->assertStringContainsString('<del>strike</del>', $html);
    }

    public function test_url_with_label_only_allows_http_https(): void
    {
        $safe = BbcodeRenderer::toHtml('see [url=https://example.com]here[/url]');
        $this->assertStringContainsString('<a href="https://example.com" target="_blank"', $safe);
        $this->assertStringContainsString('rel="noopener noreferrer ugc nofollow"', $safe);
        $this->assertStringContainsString('>here</a>', $safe);

        $bad = BbcodeRenderer::toHtml('[url=javascript:alert(1)]click[/url]');
        $this->assertStringNotContainsString('javascript:', $bad);
        $this->assertStringNotContainsString('<a ', $bad);
        $this->assertStringContainsString('click', $bad);
    }

    public function test_bare_url_is_autolinked_only_for_http_https(): void
    {
        $html = BbcodeRenderer::toHtml('check https://example.com/path?x=1 today');
        $this->assertStringContainsString('<a href="https://example.com/path?x=1"', $html);

        $skip = BbcodeRenderer::toHtml('mail me at user@example.com or ftp://nope');
        $this->assertStringNotContainsString('<a ', $skip);
    }

    public function test_img_only_renders_for_http_https(): void
    {
        $safe = BbcodeRenderer::toHtml('[img]https://cdn.example.com/p.jpg[/img]');
        $this->assertStringContainsString('<img src="https://cdn.example.com/p.jpg"', $safe);
        $this->assertStringContainsString('loading="lazy"', $safe);

        $bad = BbcodeRenderer::toHtml('[img]javascript:alert(1)[/img]');
        $this->assertStringNotContainsString('<img', $bad);
        $this->assertStringNotContainsString('javascript:', $bad);
    }

    public function test_quote_renders_with_optional_author(): void
    {
        $with = BbcodeRenderer::toHtml('[quote=alice]hi[/quote]');
        $this->assertStringContainsString('<blockquote', $with);
        $this->assertStringContainsString('alice wrote:', $with);
        $this->assertStringContainsString('hi', $with);

        $without = BbcodeRenderer::toHtml('[quote]hi[/quote]');
        $this->assertStringContainsString('<blockquote', $without);
        $this->assertStringNotContainsString('wrote:', $without);
    }

    public function test_code_block_preserves_inner_text_and_skips_bbcode(): void
    {
        $html = BbcodeRenderer::toHtml("[code][b]not bold[/b]\nplain[/code]");
        $this->assertStringContainsString('<pre', $html);
        $this->assertStringContainsString('<code>', $html);
        $this->assertStringContainsString('[b]not bold[/b]', $html);
        // No nl2br inside code blocks
        $this->assertStringNotContainsString('plain</br>', $html);
    }

    public function test_size_color_alignment_and_list(): void
    {
        $size = BbcodeRenderer::toHtml('[size=5]big[/size]');
        $this->assertStringContainsString('font-size:1.4rem', $size);

        $color = BbcodeRenderer::toHtml('[color=#ff00aa]x[/color]');
        $this->assertStringContainsString('color:#ff00aa', $color);

        $align = BbcodeRenderer::toHtml('[center]hi[/center]');
        $this->assertStringContainsString('text-align:center', $align);

        $list = BbcodeRenderer::toHtml('[list][*]one[*]two[/list]');
        $this->assertStringContainsString('<ul', $list);
        $this->assertStringContainsString('<li>one</li>', $list);
        $this->assertStringContainsString('<li>two</li>', $list);
    }

    public function test_unsupported_tags_render_as_literal_escaped_text(): void
    {
        $html = BbcodeRenderer::toHtml('[hide]secret[/hide] [spoiler=foo]bar[/spoiler]');
        $this->assertStringContainsString('[hide]secret[/hide]', $html);
        $this->assertStringContainsString('[spoiler=foo]bar[/spoiler]', $html);
    }

    public function test_xss_payloads_are_neutralised(): void
    {
        $cases = [
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            '[url=javascript:alert(1)]x[/url]',
            '[img]data:text/html,<script>alert(1)</script>[/img]',
        ];

        foreach ($cases as $input) {
            $html = BbcodeRenderer::toHtml($input);
            // No raw script tags or active attributes
            $this->assertStringNotContainsString('<script', $html, $input);
            $this->assertStringNotContainsString('<img src=x', $html, $input);
            $this->assertStringNotContainsString('javascript:', $html, $input);
        }
    }
}
