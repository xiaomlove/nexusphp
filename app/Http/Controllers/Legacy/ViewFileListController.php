<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\File;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/viewfilelist.php` (deleted in the same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md`. Tiny XHR endpoint called from
 * `public/js/common.js:22`:
 *
 *     viewfilelist(torrentid)
 *         → ajax.gets('viewfilelist.php?id=<torrentId>')
 *
 * The result is `innerHTML`-injected into the toggle-able file-list
 * block on `public/details.php`, so the wire shape (raw markup +
 * `text/xml` content type + no-cache headers) MUST stay stable.
 *
 * Original legacy flow (`public/viewfilelist.php`, 102 LOC):
 *   1. `require '../include/bittorrent.php'; dbconn();
 *      require_once get_langfile_path();` bootstrap.
 *   2. Five `header()` calls forbidding all caching.
 *   3. `Content-Type: text/xml; charset=utf-8`.
 *   4. `if (isset($CURUSER))` — guest → empty body, authed → table.
 *   5. Inline `<style>` block defining `.fileicon.fi-<cat>` colour
 *      classes, then `<table class="main">` with one row per file:
 *      colored extension badge + filename, plus formatted size.
 *
 * Replacement contract (this controller):
 *   - Public route — same as the legacy script. The `$CURUSER` gate
 *     becomes a `LegacyContext::user() === null` check returning the
 *     empty-body envelope, mirroring legacy parity. Placing the
 *     route under `auth.nexus:nexus-web` would redirect guests to
 *     `/login.php?returnto=...`, and the calling JS uses synchronous
 *     `ajax.gets` whose `responseText` would then be the login-page
 *     HTML — splicing that into the toggle DIV is a UX bug we
 *     explicitly avoid.
 *   - `Content-Type: text/xml; charset=utf-8` and the same five
 *     no-cache headers, byte-for-byte (the `Cache-Control` value is
 *     re-rendered by Symfony's `Response::prepare()` — see
 *     `GetExtInfoAjaxController` for the same caveat).
 *   - 200 with empty body for: guest, missing/zero `?id`, or torrent
 *     with no `files` rows AFTER the table chrome (legacy emitted
 *     the `<style>` + `<table>` open / column-headers / `</table>`
 *     even when the loop had zero iterations). We preserve that
 *     because the calling JS `innerHTML`-replaces the content; an
 *     empty table is a clearer signal to the user than no markup.
 *   - 200 with markup body for an authed request that resolves to
 *     a torrent with `files` rows: inline `<style>`, header row,
 *     one row per file (colored extension badge + filename,
 *     `Format::size`-formatted size).
 *
 * Filename HTML escaping: the badge label uses `htmlspecialchars`
 * on the (already lower-cased + ctype-alnum-validated) extension
 * letters, and the filename column uses `htmlspecialchars` on the
 * raw `files.filename` value. A torrent uploader who manages to
 * land an HTML-tag in a filename row would get inert text in the
 * XHR response.
 */
class ViewFileListController extends Controller
{
    /**
     * Map of file extension → badge category. Keys are lowercase
     * extensions, values are CSS-class category suffixes. Extensions
     * not in any category collapse to the `other` bucket via the
     * default branch in `categoryFor()`.
     *
     * @var array<string, array<int, string>>
     */
    private const EXTENSION_CATEGORIES = [
        'video' => ['mkv', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'ts', 'm2ts', 'mts', 'webm', 'mpg', 'mpeg', 'vob', 'rm', 'rmvb', 'm4v', '3gp', 'ogv', 'asf', 'divx', 'mxf'],
        'audio' => ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac', 'opus', 'wma', 'ape', 'alac', 'dts', 'ac3', 'mka', 'mp2', 'mid', 'midi', 'tak', 'tta', 'wv'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'heic', 'heif', 'ico', 'psd', 'raw', 'arw', 'cr2', 'nef'],
        'subtitle' => ['srt', 'ass', 'ssa', 'sub', 'idx', 'vtt', 'sup', 'smi', 'sbv'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'zst', 'lz', 'lzma', 'tbz2', 'tgz', 'txz', 'cab', 'arj'],
        'iso' => ['iso', 'img', 'mds', 'mdf', 'bin', 'cue', 'nrg', 'dmg', 'vhd', 'vmdk'],
        'document' => ['pdf', 'epub', 'mobi', 'azw', 'azw3', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'djvu', 'fb2', 'chm', 'odt', 'ods', 'odp'],
        'text' => ['txt', 'md', 'log', 'sfv', 'md5', 'sha1', 'sha256', 'par', 'par2', 'json', 'xml', 'yaml', 'yml', 'csv', 'ini'],
        'nfo' => ['nfo'],
        'code' => ['php', 'js', 'ts', 'py', 'rb', 'go', 'rs', 'c', 'h', 'cpp', 'hpp', 'cs', 'java', 'sh', 'sql', 'html', 'css', 'scss', 'vue'],
        'exec' => ['exe', 'msi', 'app', 'deb', 'rpm', 'apk', 'dmg', 'pkg', 'run', 'bat', 'cmd', 'ps1', 'jar'],
        'torrent' => ['torrent'],
    ];

    /**
     * Inline `<style>` block, kept verbatim from the legacy script
     * so the rendered badge palette matches what the front-end has
     * been showing for years.
     */
    private const BADGE_STYLE = <<<'CSS'
<style>
.fileicon { display:inline-block; box-sizing:border-box; min-width:38px; padding:1px 5px; margin-right:6px; border-radius:3px; font:10px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight:bold; letter-spacing:.3px; color:#fff; text-align:center; vertical-align:1px; text-transform:uppercase; }
.fileicon.fi-video    { background:#3498db; }
.fileicon.fi-audio    { background:#27ae60; }
.fileicon.fi-image    { background:#9b59b6; }
.fileicon.fi-subtitle { background:#e84393; }
.fileicon.fi-archive  { background:#e67e22; }
.fileicon.fi-iso      { background:#34495e; }
.fileicon.fi-document { background:#c0392b; }
.fileicon.fi-text     { background:#7f8c8d; }
.fileicon.fi-nfo      { background:#16a085; }
.fileicon.fi-code     { background:#2c3e50; }
.fileicon.fi-exec     { background:#d35400; }
.fileicon.fi-torrent  { background:#8e44ad; }
.fileicon.fi-other    { background:#95a5a6; }
</style>
CSS;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $body = '';

        $user = $this->context->user();
        if ($user !== null) {
            $torrentId = (int) $request->query('id', 0);
            if ($torrentId > 0) {
                $body = $this->renderBody($torrentId);
            }
        }

        $response = new Response($body, 200);
        $response->headers->replace([
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').'GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);

        return $response;
    }

    private function renderBody(int $torrentId): string
    {
        $rows = File::query()
            ->where('torrent', $torrentId)
            ->orderBy('id')
            ->get(['filename', 'size']);

        $body = self::BADGE_STYLE;
        $body .= '<table class="main" border="1" cellspacing="0" cellpadding="5">'."\n";
        $body .= '<tr><td class="colhead">Path</td>'
            .'<td class="colhead" align="center"><img class="size" src="pic/trans.gif" alt="size" /></td></tr>'."\n";

        foreach ($rows as $row) {
            $filename = (string) $row->filename;
            $size = (int) $row->size;
            $body .= '<tr>'
                .'<td class="rowfollow">'.$this->renderBadge($filename).htmlspecialchars($filename).'</td>'
                .'<td class="rowfollow" align="right">'.htmlspecialchars(Format::size($size)).'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }

    private function renderBadge(string $filename): string
    {
        $dot = strrpos($filename, '.');
        $ext = $dot !== false ? strtolower(substr($filename, $dot + 1)) : '';

        if ($ext === '' || strlen($ext) > 5 || ! ctype_alnum($ext)) {
            $cat = 'other';
            $label = '?';
        } else {
            $cat = $this->categoryFor($ext);
            $label = strtoupper($ext);
        }

        return '<span class="fileicon fi-'.htmlspecialchars($cat).'" title="'.htmlspecialchars($cat).'">'
            .htmlspecialchars($label)
            .'</span>';
    }

    private function categoryFor(string $ext): string
    {
        foreach (self::EXTENSION_CATEGORIES as $cat => $list) {
            if (in_array($ext, $list, true)) {
                return $cat;
            }
        }

        return 'other';
    }
}
