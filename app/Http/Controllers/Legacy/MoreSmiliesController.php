<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/moresmilies.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — chrome-less popup window that
 * renders a 3-column smiley grid. The legacy compose helper in
 * `include/functions.php` opens it via
 *
 *     window.open("moresmilies.php?form=<f>&text=<t>",
 *                 "mywin", "height=500,width=500, ...")
 *
 * and each smiley `<a>` calls back into `window.opener` to inject
 * the chosen `[emN]` token into the calling form's textarea.
 *
 * The migrated controller preserves that contract exactly:
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authed → 191 smilies in a 3-column `<table class="lista">`,
 *     same `pic/smilies/<i>.gif` URLs the legacy file emits.
 *   - `form` / `text` query params are echoed into the inline
 *     JavaScript `SmileIT()` call with `htmlspecialchars()` to
 *     mirror the legacy `htmlspecialchars($_GET[...])` calls.
 *
 * The legacy script depended on `$lang_moresmilies` for the page
 * `<title>` and the close-link label. Following the existing
 * Phase 2 controllers (ThanksController, TakeContactController,
 * PreviewController etc.) the migrated controller bakes English
 * literals in — none of the prior migrations preserved the
 * legacy `lang_*` lookup, and the popup is internal chrome that
 * never moved to the i18n catalog.
 */
class MoreSmiliesController extends Controller
{
    /**
     * Number of smiley GIFs under `public/pic/smilies/`. Mirrors the
     * legacy `for ($i = 1; $i < 192; $i++)` loop.
     */
    private const SMILEY_COUNT = 191;

    public function __invoke(Request $request): Response
    {
        $form = htmlspecialchars((string) $request->query('form', ''));
        $text = htmlspecialchars((string) $request->query('text', ''));

        $rows = '';
        for ($i = 1, $count = 0; $i <= self::SMILEY_COUNT; $i++) {
            if ($count % 3 === 0) {
                $rows .= "\n<tr>";
            }
            $rows .= sprintf(
                "\n\t<td class=\"lista\" align=\"center\">"
                ."<a href=\"javascript: SmileIT('[em%d]','%s','%s')\">"
                .'<img src="pic/smilies/%d.gif" alt="" ></a></td>',
                $i,
                $form,
                $text,
                $i
            );
            $count++;
            if ($count % 3 === 0) {
                $rows .= "\n</tr>";
            }
        }

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>More Clickable Smilies</title>
<style type="text/css">
img {border: none;}
body {color: #000000; background-color: #ffffff}
</style>
</head>
<body>
<script type="text/javascript">
function SmileIT(smile,form,text){
   window.opener.document.forms[form].elements[text].value = window.opener.document.forms[form].elements[text].value+" "+smile+" ";
   window.opener.document.forms[form].elements[text].focus();
   window.close();
}
</script>

<table class="lista" width="100%" cellpadding="1" cellspacing="1">{$rows}
</table>
<div align="center">
 <a href="javascript: window.close()">Close</a>
</div>
</body>
</html>
HTML;

        return new Response($html);
    }
}
