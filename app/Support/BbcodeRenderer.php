<?php

namespace App\Support;

/**
 * Self-contained, dependency-free BBCode → HTML renderer for the
 * Livewire forum views. Handles a safe subset of the tags supported
 * by include/functions.php::format_comment().
 *
 * What's covered (rendered as HTML):
 *   [b] [i] [u] [s]            inline emphasis
 *   [code]                     fenced <pre><code>
 *   [quote]                    blockquote (with optional [quote=author])
 *   [url] / [url=...]          anchor (http/https only)
 *   [img] / [img=...]          inline image (http/https only, lazy)
 *   [size=N]                   1..7 → font-size buckets
 *   [color=...]                hex / a-z color names
 *   [center] [left] [right]    aligned block
 *   [list] [*]                 bulleted list
 *   [hr]                       horizontal rule
 *   bare http(s) URLs          auto-linked
 *
 * Tags NOT covered (output is left as escaped literal text — users
 * can click the "Reply / formatted view" chip to see the legacy render
 * with [hide], [spoiler], [attach], [youtube], [video], [audio], etc.):
 *   [hide] [spoiler] [attach] [youtube] [video] [audio] [emN]
 *   [font=...]
 */
final class BbcodeRenderer
{
    /** @var array<int, string> */
    private const SIZE_MAP = [
        1 => '0.7rem',
        2 => '0.85rem',
        3 => '1rem',
        4 => '1.15rem',
        5 => '1.4rem',
        6 => '1.7rem',
        7 => '2rem',
    ];

    public static function toHtml(?string $body): string
    {
        $body = (string) ($body ?? '');
        if ($body === '') {
            return '';
        }

        // 1. Escape everything first — anything we don't actively
        //    transform must end up as plain literal text.
        $s = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        // 2. [code]…[/code] — pulled out into a placeholder set so its
        //    contents are NOT touched by subsequent replacements.
        $codeBlocks = [];
        $s = (string) preg_replace_callback(
            '~\[code\](.+?)\[/code\]~is',
            static function (array $m) use (&$codeBlocks): string {
                $key = '<<<NEXUS_CODE_'.count($codeBlocks).'>>>';
                $codeBlocks[$key] = '<pre class="rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-950"><code>'
                    .$m[1]
                    .'</code></pre>';

                return $key;
            },
            $s,
        );

        // 3. Inline emphasis.
        $s = (string) preg_replace(
            ['~\[b\](.+?)\[/b\]~is', '~\[i\](.+?)\[/i\]~is', '~\[u\](.+?)\[/u\]~is', '~\[s\](.+?)\[/s\]~is'],
            ['<strong>$1</strong>', '<em>$1</em>', '<u>$1</u>', '<del>$1</del>'],
            $s,
        );

        // 4. [quote] / [quote=author].
        $s = (string) preg_replace_callback(
            '~\[quote=([^\]]{1,80})\](.+?)\[/quote\]~is',
            static fn (array $m): string => '<blockquote class="my-2 border-l-4 border-zinc-300 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950/40">'
                .'<cite class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400">'.$m[1].' wrote:</cite>'
                .'<div>'.$m[2].'</div>'
                .'</blockquote>',
            $s,
        );
        $s = (string) preg_replace(
            '~\[quote\](.+?)\[/quote\]~is',
            '<blockquote class="my-2 border-l-4 border-zinc-300 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950/40">$1</blockquote>',
            $s,
        );

        // 5. [url=...]label[/url] and [url]URL[/url]. Only http/https.
        $s = (string) preg_replace_callback(
            '~\[url=([^\]\s]{1,2000})\](.+?)\[/url\]~is',
            static fn (array $m): string => self::buildAnchor($m[1], $m[2]),
            $s,
        );
        $s = (string) preg_replace_callback(
            '~\[url\]([^\[\s]{1,2000})\[/url\]~is',
            static fn (array $m): string => self::buildAnchor($m[1], $m[1]),
            $s,
        );

        // 6. [img]URL[/img] and [img=URL]. Only http/https.
        $s = (string) preg_replace_callback(
            '~\[img\]([^\[\s\'"<>]{1,2000})\[/img\]~i',
            static fn (array $m): string => self::buildImage($m[1]),
            $s,
        );
        $s = (string) preg_replace_callback(
            '~\[img=([^\[\s\'"<>]{1,2000})\]~i',
            static fn (array $m): string => self::buildImage($m[1]),
            $s,
        );

        // 7. [size=N] / [color=…].
        $s = (string) preg_replace_callback(
            '~\[size=([1-7])\](.+?)\[/size\]~is',
            static function (array $m): string {
                $rem = self::SIZE_MAP[(int) $m[1]] ?? '1rem';

                return '<span style="font-size:'.$rem.'">'.$m[2].'</span>';
            },
            $s,
        );
        $s = (string) preg_replace_callback(
            '~\[color=([\#0-9a-zA-Z]{1,15})\](.+?)\[/color\]~is',
            static function (array $m): string {
                $color = self::sanitiseColor($m[1]);
                if ($color === null) {
                    return $m[2];
                }

                return '<span style="color:'.$color.';word-break:break-word">'.$m[2].'</span>';
            },
            $s,
        );

        // 8. Alignment.
        foreach (['left', 'center', 'right'] as $align) {
            $s = (string) preg_replace(
                '~\['.$align.'\](.*?)\[/'.$align.'\]~is',
                '<div style="text-align:'.$align.'">$1</div>',
                $s,
            );
        }

        // 9. [hr].
        $s = str_replace('[hr]', '<hr class="my-3 border-zinc-200 dark:border-zinc-700">', $s);

        // 10. [list]…[/list] with [*] items.
        $s = (string) preg_replace_callback(
            '~\[list\](.+?)\[/list\]~is',
            static function (array $m): string {
                $items = preg_split('~\[\*\]~', $m[1]) ?: [];
                $items = array_values(array_filter(array_map('trim', $items), static fn (string $i): bool => $i !== ''));
                if ($items === []) {
                    return '';
                }
                $li = '';
                foreach ($items as $i) {
                    $li .= '<li>'.$i.'</li>';
                }

                return '<ul class="list-disc pl-5">'.$li.'</ul>';
            },
            $s,
        );

        // 11. Auto-link bare http(s) URLs that survived steps 5/6.
        $s = (string) preg_replace_callback(
            '~(?<![">=\'/])\b(https?://[^\s<]+[^\s<.,;:!?\)])~i',
            static fn (array $m): string => self::buildAnchor($m[1], $m[1]),
            $s,
        );

        // 12. Linebreaks. Skip <br> inside <pre> code blocks (already
        //     placeholders).
        $s = nl2br($s, false);

        // 13. Restore [code] blocks.
        if ($codeBlocks !== []) {
            $s = strtr($s, $codeBlocks);
        }

        return $s;
    }

    private static function buildAnchor(string $rawUrl, string $label): string
    {
        $url = self::sanitiseUrl($rawUrl);
        if ($url === null) {
            return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer ugc nofollow">'.$label.'</a>';
    }

    private static function buildImage(string $rawUrl): string
    {
        $url = self::sanitiseUrl($rawUrl);
        if ($url === null) {
            return '';
        }

        return '<img src="'.$url.'" alt="" loading="lazy" decoding="async" style="max-width:100%;height:auto">';
    }

    /**
     * Allow only http/https URLs and protocol-relative // URLs.
     * Returns the escaped URL or null if rejected.
     */
    private static function sanitiseUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('~^https?://[^\s<>"\']+$~i', $url) && ! preg_match('~^//[^\s<>"\']+$~', $url)) {
            return null;
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function sanitiseColor(string $color): ?string
    {
        $color = trim($color);
        if ($color === '') {
            return null;
        }
        if (preg_match('~^\#[0-9a-f]{3,8}$~i', $color)) {
            return $color;
        }
        if (preg_match('~^[a-z]{3,15}$~i', $color)) {
            return strtolower($color);
        }

        return null;
    }
}
