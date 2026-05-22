<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\Bencode;

/**
 * Replacement for `public/torrent_info.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. `user_can('torrentstructure', true)` — permission gate
 *      (default class 8 = Insane User).
 *   3. Read the torrent name from `torrents` table, read the
 *      `.torrent` file from disk, decode with `Bencode::load()`.
 *   4. Render the bencode structure as an expandable HTML tree
 *      (dictionary / list / integer / string nodes).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated user without `torrentstructure` permission →
 *     `abort(403)`.
 *   - Valid `?id=<n>` with existing torrent → 200 with chrome-less
 *     HTML envelope containing the bencode tree.
 *   - Missing/invalid `?id` or missing file → `abort(404)`.
 */
class TorrentInfoController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        if (! user_can('torrentstructure')) {
            abort(403);
        }

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $row = NexusDB::table('torrents')
            ->where('id', $id)
            ->select(['name'])
            ->first();

        if ($row === null) {
            abort(404);
        }

        $torrentDir = Setting::get('main.torrent_dir');
        $fn = getFullDirectory("{$torrentDir}/{$id}.torrent");

        if (! is_file($fn) || ! is_readable($fn)) {
            abort(404);
        }

        $dict = Bencode::load($fn);
        $name = htmlspecialchars((string) ((array) $row)['name']);
        $tree = $this->buildTree(['root' => $dict]);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Torrent Info</title>
<style type="text/css">
ul ul { margin-left: 15px; }
ul, li { padding: 0px; margin: 0px; list-style-type: none; color: #000; font-weight: normal;}
ul a, li a { color: #009; text-decoration: none; font-weight: normal; }
li { display: inline; }
ul > li { display: list-item; }
li div.string  {padding: 3px;}
li div.integer {padding: 3px;}
li div.dictionary {padding: 3px;}
li div.list {padding: 3px;}
li div.string span.icon {color:#090;padding: 2px;}
li div.integer span.icon {color:#990;padding: 2px;}
li div.dictionary span.icon {color:#909;padding: 2px;}
li div.list span.icon {color:#009;padding: 2px;}
li span.title {font-weight: bold;}
</style>
</head>
<body>
<div align="center"><h1>{$name}</h1>
<table width="750" border="1" cellspacing="0" cellpadding="5"><td>
<ul id="torrent-structure">
{$tree}
</ul>
</td></table></div>
</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Recursively build the HTML tree from a decoded bencode structure.
     */
    private function buildTree(array $array, string $parent = ''): string
    {
        $ret = '';
        foreach ($array as $item => $value) {
            $valueLength = strlen(Bencode::encode($value));
            if (is_iterable($value)) {
                $valueArray = is_array($value) ? $value : iterator_to_array($value);
                $type = $this->isIndexedArray($valueArray) ? 'list' : 'dictionary';
                $itemEsc = htmlspecialchars((string) $item);
                $ret .= "<li><div align='left' class='".$type."'><a href='javascript:void(0);' onclick='jQuery(this).parent().next(\"ul\").toggle()'> + <span class=title>[".$itemEsc."]</span> <span class='icon'>(".ucfirst($type).')</span> <span class=length>['.$valueLength.']</span></a></div>';
                $ret .= "<ul style='display:none'>".$this->buildTree($valueArray, (string) $item).'</ul></li>';
            } else {
                $type = is_int($value) ? 'integer' : 'string';
                $displayValue = ($parent === 'info' && $item === 'pieces')
                    ? '0x'.bin2hex(substr((string) $value, 0, 25)).'...'
                    : htmlspecialchars((string) $value);
                $itemEsc = htmlspecialchars((string) $item);
                $ret .= '<li><div align=left class='.$type.'> - <span class=title>['.$itemEsc.']</span> <span class=icon>('.ucfirst($type).')</span> <span class=length>['.$valueLength.']</span>: <span class=value>'.$displayValue.'</span></div></li>';
            }
        }

        return $ret;
    }

    /**
     * Check if an array is numerically indexed (a "list" in bencode terms).
     */
    private function isIndexedArray(array $arr): bool
    {
        return count(array_filter(array_keys($arr), 'is_string')) === 0;
    }
}
