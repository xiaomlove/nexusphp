<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/contactstaff.php` (deleted in the same PR).
 *
 * Phase 2 batch #5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `stdhead($lang_contactstaff['head_contact_staff']);` +
 *      `begin_main_frame();` + `begin_compose(..., 'new');` +
 *      `end_compose();` + `end_main_frame();` + `stdfoot();` —
 *      renders a chrome-wrapped compose form with the BBCode editor
 *      (`textbbcode()`) and a smilies panel, posting to
 *      `takecontact.php`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authed → 200 with a chrome-less, self-contained HTML envelope
 *     wrapping a basic subject + body form that posts to
 *     `/takecontact.php` (already CSRF-exempt in
 *     `App\Http\Middleware\VerifyCsrfToken`). The BBCode editor /
 *     smilies panel are intentionally not reproduced here — they
 *     depend on the legacy `textbbcode()` helper, which entangles
 *     with `stdhead()` and a pile of inline JavaScript. Users can
 *     still paste BBCode and `[emN]` tokens by hand; the resulting
 *     POST is identical.
 *
 * Matches the `MoreSmiliesController` chrome-less precedent — the
 * full legacy chrome will come back in Phase 5 once `stdhead()` /
 * `stdfoot()` get native Blade partials.
 */
class ContactStaffController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $body = '<h1>Contact Staff</h1>'."\n"
            .'<form id="compose" method="post" name="compose" action="takecontact.php">'."\n"
            .'<h2>Send message to Staff</h2>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="rowhead">Subject</td><td>'
            .'<input type="text" name="subject" maxlength="100" size="60"></td></tr>'."\n"
            .'<tr><td class="rowhead" valign="top">Body</td><td>'
            .'<textarea name="body" rows="12" cols="60"></textarea></td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" value="Send It!" class="btn"></td></tr>'."\n"
            .'</table>'."\n"
            .'</form>'."\n";

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Contact Staff</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
