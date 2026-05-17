<?php

namespace App\Support;

/**
 * Stateless HTML builders for BBCode tags, extracted from
 * `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `formatUrl()`        (`<a>` tag with optional class and `target=_blank`)
 *   - `formatAdUrl()`      (ad-redirect URL — delegates to `url()`)
 *   - `formatCode()`       (`[code]` block with `<pre><code>` body)
 *   - `formatImg()`        (`<img>` with optional resizer onload hook)
 *   - `formatFlash()`      (`<object>`/`<embed>` Shockwave-Flash)
 *   - `formatFlv()`        (`<object>`/`<embed>` flvplayer.swf)
 *   - `formatYoutube()`    (`<iframe>` YouTube embed)
 *   - `formatVideo()`      (HTML5 `<video>` element)
 *   - `formatAudio()`      (HTML5 `<audio>` element)
 *   - `formatSpoiler()`    (`<details>`/`<summary>` collapsible)
 *   - `formatHidden()`     (`<span class="hidden-text">` wrapper)
 *   - `formatTextAlign()`  (`<div style="text-align: …">`)
 *
 * all collapse into the static methods below. Each returns **bare**
 * HTML — the legacy proxies in `include/functions.php` are
 * responsible for the `addTempCode()` placeholder dance (and for
 * `filter_src()` URL sanitisation on the embed methods).
 *
 * Keeping `addTempCode()` and `filter_src()` in the proxy layer
 * means this class stays pure: no DI, no DB, no global state. Same
 * convention as {@see Imdb}, {@see Ratio}, {@see Validators},
 * {@see Format}, {@see Strings}, {@see Time}, {@see Codec}.
 */
final class BBCode
{
    /**
     * Render an `<a href="…">…</a>` link.
     *
     * Mirrors the legacy `formatUrl()` exactly:
     *   - empty `$text` falls back to the URL itself
     *   - `$newWindow === true` (loose) appends `target="_blank"`;
     *     anything else (false, "0", null) does NOT
     *   - `$linkClass`, if non-empty, becomes `class="…"`
     *
     * The `$url` is interpolated as-is — sanitisation is the
     * caller's job (the legacy proxy does it via `filter_src()`
     * before reaching here for image/video embeds, but URLs from
     * `[url]…[/url]` BBCode tags are written verbatim, matching
     * the pre-existing contract).
     */
    public static function url(string $url, bool $newWindow = false, string $text = '', string $linkClass = ''): string
    {
        if (! $text) {
            $text = $url;
        }
        $classAttr = $linkClass !== '' ? " class=\"$linkClass\"" : '';
        $targetAttr = $newWindow ? ' target="_blank"' : '';

        return "<a$classAttr href=\"$url\"$targetAttr>$text</a>";
    }

    /**
     * Render an ad-tracking redirect link. Wraps the destination URL
     * in `adredir.php?id=…&amp;url=…` (rawurlencoded) and delegates
     * to {@see url} for the actual anchor.
     *
     * Pinned legacy quirk: the `&amp;` separator is HTML-encoded
     * inline (not `&`). The legacy `formatAdUrl()` does the same —
     * removing the encoding would double-encode when the surrounding
     * HTML pipeline runs `htmlspecialchars()`. Don't "fix" it.
     */
    public static function adUrl(int|string $adid, string $url, string $content, bool $newWindow = true): string
    {
        return self::url('adredir.php?id='.$adid.'&amp;url='.rawurlencode($url), $newWindow, $content);
    }

    /**
     * Render a `[code]` block. Used for verbatim quoting of source
     * code in forum posts and torrent descriptions.
     *
     * The `$label` is the language-aware "Code" string — the legacy
     * proxy threads `nexus_trans('label.text_code')` through here.
     * Pinning the label as a parameter keeps the class free of
     * Laravel-specific helpers.
     */
    public static function code(string $text, string $label): string
    {
        return '<br /><div class="codetop">'.$label.'</div><div class="codemain"><pre><code>'.$text.'</code></pre></div><br />';
    }

    /**
     * Render an inline `<img>`. The `$src` is interpolated verbatim
     * — the legacy proxy is expected to have passed it through
     * `filter_src()` first, which short-circuits on cross-host,
     * non-image-extension, or non-existent paths.
     *
     * Empty `$src` short-circuits to an empty string (the legacy
     * `formatImg()` does the same after `filter_src()`).
     *
     * When `$enableResizer` is true the legacy `Scale()` JS hook is
     * attached via `onload`, and the resulting element is tagged
     * with `data-zoomable` for the lightbox script. The error
     * fallback (`onerror="handleImageError(this, ...);"`) is always
     * emitted regardless of resizer flag.
     */
    public static function img(string $src, bool $enableResizer, int $maxWidth, int $maxHeight, string $imgId = ''): string
    {
        if (empty($src)) {
            return '';
        }
        $resizerAttrs = $enableResizer
            ? " onload=\"Scale(this, $maxWidth, $maxHeight);\" data-zoomable "
            : '';

        return "<img style=\"max-width: 100%\" id=\"$imgId\" alt=\"image\" src=\"$src\"".$resizerAttrs." onerror=\"handleImageError(this, '$src');\" />";
    }

    /**
     * Render a Shockwave-Flash `<object>` embed. Defaults to
     * 500×300 if the caller omits dimensions.
     *
     * Yes, Flash is dead — this is preserved for backward
     * compatibility with existing posts. Remove the BBCode tag
     * from the parser in a separate PR if you want to retire it.
     */
    public static function flash(string $src, int|string $width = 0, int|string $height = 0): string
    {
        if (empty($src)) {
            return '';
        }
        if (! $width) {
            $width = 500;
        }
        if (! $height) {
            $height = 300;
        }

        return "<object width=\"$width\" height=\"$height\"><param name=\"movie\" value=\"$src\" /><embed src=\"$src\" width=\"$width\" height=\"$height\" type=\"application/x-shockwave-flash\"></embed></object>";
    }

    /**
     * Render an FLV-player `<object>` embed via `flvplayer.swf`.
     * Defaults to 320×240. Same Flash-is-dead disclaimer as
     * {@see flash}.
     */
    public static function flv(string $src, int|string $width = 0, int|string $height = 0): string
    {
        if (empty($src)) {
            return '';
        }
        if (! $width) {
            $width = 320;
        }
        if (! $height) {
            $height = 240;
        }

        return "<object width=\"$width\" height=\"$height\"><param name=\"movie\" value=\"flvplayer.swf?file=$src\" /><param name=\"allowFullScreen\" value=\"true\" /><embed src=\"flvplayer.swf?file=$src\" type=\"application/x-shockwave-flash\" allowfullscreen=\"true\" width=\"$width\" height=\"$height\"></embed></object>";
    }

    /**
     * Render a YouTube `<iframe>` embed. Defaults to 560×315.
     *
     * The `$src` URL is parsed for its `v=` query parameter; if
     * none is found, the video-id is empty (which produces a
     * broken iframe — preserved verbatim from the legacy
     * `formatYoutube()` to avoid surprising existing call sites
     * that may rely on the broken-iframe sentinel).
     */
    public static function youtube(string $src, int|string $width = 0, int|string $height = 0): string
    {
        if (empty($src)) {
            return '';
        }
        if (! $width) {
            $width = 560;
        }
        if (! $height) {
            $height = 315;
        }
        $queryString = parse_url($src, PHP_URL_QUERY);
        $parameters = [];
        if (is_string($queryString)) {
            parse_str($queryString, $parameters);
        }
        $videoId = (string) ($parameters['v'] ?? '');

        return sprintf(
            '<iframe width="%s" height="%s" src="https://www.youtube.com/embed/%s" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
            $width,
            $height,
            $videoId
        );
    }

    /**
     * Render an HTML5 `<video>` element with a download fallback
     * anchor. Defaults to 560×315.
     */
    public static function video(string $src, int|string $width = 0, int|string $height = 0): string
    {
        if (empty($src)) {
            return '';
        }
        if (! $width) {
            $width = 560;
        }
        if (! $height) {
            $height = 315;
        }

        return "<video controls width=\"$width\" height=\"$height\"><source src=\"$src\" /><a href=\"$src\">$src</a></video>";
    }

    /**
     * Render an HTML5 `<audio>` element with a download fallback
     * anchor. No dimension parameters.
     */
    public static function audio(string $src): string
    {
        if (empty($src)) {
            return '';
        }

        return "<audio controls><source src=\"$src\" /><a href=\"$src\">$src</a></audio>";
    }

    /**
     * Render a `<span class="hidden-text">…</span>` wrapper.
     * Thin delegation to {@see Strings::hidden} — kept on the
     * BBCode surface for symmetry with the other `[…]` tag helpers.
     */
    public static function hidden(string $content): string
    {
        return Strings::hidden($content);
    }

    /**
     * Render a `<details>`/`<summary>` spoiler block.
     *
     * The `$defaultTitle` is the language-aware default — the
     * legacy proxy threads `$lang_functions['spoiler_default_title']`
     * through here. The empty-string check is loose: `'0'` is
     * considered empty by the legacy contract (matching the
     * `!$title` test) and falls through to the default.
     */
    public static function spoiler(string $content, string $title, string $defaultTitle, bool $defaultCollapsed = true): string
    {
        if (! $title) {
            $title = $defaultTitle;
        }
        $contentClass = $defaultCollapsed ? '' : ' open';

        return sprintf(
            '<details%s><summary>%s</summary>%s</details>',
            $contentClass,
            $title,
            $content
        );
    }

    /**
     * Render a `<div style="text-align: …">…</div>`. The `$align`
     * value (`left`, `center`, `right`, `justify`) is interpolated
     * verbatim — the legacy proxy is called only from the BBCode
     * parser with a hard-coded set of values, so no validation is
     * performed here.
     */
    public static function textAlign(string $text, string $align): string
    {
        return sprintf('<div style="text-align: %s">%s</div>', $align, $text);
    }

    /**
     * Rewrite `[quote]` / `[quote=author]` blocks as
     * `<fieldset><legend>` HTML. Mismatched or out-of-order tag pairs
     * cause the input to be returned verbatim (the legacy contract —
     * unmatched bbcode is shown raw to the user).
     *
     * The `$quoteLabel` is the localized "Quote" string the legacy
     * proxy resolves via `nexus_trans("label.text_quote")` and threads
     * through to this helper.
     */
    public static function quotes(string $text, string $quoteLabel): string
    {
        preg_match_all('/\[quote.*?\]/i', $text, $result, PREG_PATTERN_ORDER);
        $openTags = $result[0];
        preg_match_all('/\[\/quote\]/i', $text, $result, PREG_PATTERN_ORDER);
        $closeTags = $result[0];
        if (count($openTags) !== count($closeTags)) {
            return $text;
        }

        $openPositions = [];
        $pos = -1;
        foreach ($openTags as $needle) {
            $openPositions[] = $pos = strpos($text, $needle, $pos + 1);
        }
        $closePositions = [];
        $pos = -1;
        foreach ($closeTags as $needle) {
            $closePositions[] = $pos = strpos($text, $needle, $pos + 1);
        }
        for ($i = 0, $n = count($openPositions); $i < $n; $i++) {
            if ($openPositions[$i] > $closePositions[$i]) {
                return $text;
            }
        }

        $text = preg_replace('/\[quote\]/i', '<fieldset><legend> '.$quoteLabel.' </legend><br />', $text);
        $text = preg_replace('/\[quote=(.+?)\]/i', '<fieldset><legend> '.$quoteLabel.': \\1 </legend><br />', $text);
        $text = preg_replace('/\[\/quote\]/i', '</fieldset><br />', $text);

        return (string) $text;
    }

    /**
     * Strip BBCode and `[emN]` smilies from `$text`. Parameter-less
     * tags (`[b]`, `[/url]`, etc.) are removed by literal `str_replace`;
     * parametered tags (`[url=…]`, `[color=…]`, `[youtube …]`, etc.) by
     * regex. `[emN]` is resolved via `$emojiMap` (default empty string
     * for unknown indices, mirroring the legacy `nexus_config('emoji')`
     * lookup). Finally, `strip_tags()` strips any remaining HTML and
     * the result is `trim()`'d.
     */
    public static function stripAll(string $text, array $emojiMap): string
    {
        $literalTags = [
            '[*]', '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[s]', '[/s]',
            '[pre]', '[/pre]', '[quote]', '[/quote]',
            '[/color]', '[/font]', '[/size]', '[/url]', '[/youtube]', '[/spoiler]',
        ];
        $text = str_replace($literalTags, '', $text);
        $text = (string) preg_replace(
            '/\[url=.*\]|\[color=.*\]|\[font=.*\]|\[size=.*\]|\[youtube.*\]|\[spoiler.*\]/isU',
            '',
            $text,
        );
        $text = (string) preg_replace_callback(
            '/\[em([1-9][0-9]*)\]/isU',
            fn (array $matches): string => $emojiMap[$matches[1]] ?? '',
            $text,
        );

        return trim(strip_tags($text));
    }
}
