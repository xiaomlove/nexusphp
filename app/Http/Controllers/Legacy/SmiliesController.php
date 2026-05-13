<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/smilies.php` (deleted in the same PR).
 *
 * Phase 2 batch #4 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `stdhead();` + `begin_main_frame();` + `insert_smilies_frame();`
 *      + `end_main_frame();` + `stdfoot();` — renders a reference grid
 *      of `[em1]`..`[em191]` smiley tokens with their matching
 *      `pic/smilies/<i>.gif` previews.
 *   3. Linked from the legacy compose helper in
 *      `include/functions.php#smiles_panel()` as a "smilies legend"
 *      help page (and rendered into a small browser window from
 *      that link via `target="_blank"`).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authed → 200 with a chrome-less reference page keyed by
 *     `[emN]` → `pic/smilies/N.gif`.
 *
 * Following the existing Phase 2 controllers (MoreSmiliesController
 * etc.) the migrated controller bakes English literals in and emits
 * a self-contained HTML envelope without the legacy `stdhead()` /
 * `stdfoot()` chrome — the page is reached from a help link and
 * stands on its own.
 */
class SmiliesController extends Controller
{
    /**
     * Number of smiley GIFs under `public/pic/smilies/`. Mirrors the
     * legacy `for ($i = 1; $i < 192; $i++)` loop in
     * `include/functions.php#insert_smilies_frame()`.
     */
    private const SMILEY_COUNT = 191;

    public function __invoke(Request $request): Response
    {
        $rows = '';
        for ($i = 1; $i <= self::SMILEY_COUNT; $i++) {
            $rows .= sprintf(
                '<tr><td>[em%d]</td><td><img src="pic/smilies/%d.gif" alt="[em%d]" /></td></tr>'."\n",
                $i,
                $i,
                $i,
            );
        }

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Smilies</title>
<style type="text/css">
img {border: none;}
body {color: #000000; background-color: #ffffff}
</style>
</head>
<body>
<h2 align="left">Smilies</h2>
<table border="1" cellspacing="0" cellpadding="5">
<tr><td class="colhead">Type...</td><td class="colhead">To make a...</td></tr>
{$rows}</table>
</body>
</html>
HTML;

        return new Response($html);
    }
}
