<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/preview.php` (deleted in the same PR).
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. echoes a small `<table>` containing `format_comment($_POST['body'])`.
 *
 * The migrated controller preserves that contract:
 *   - Guest → redirect to `login.php` (auth.nexus middleware).
 *   - POST/GET with `body` → returns the same HTML fragment.
 *   - Empty body → still returns the empty table (the legacy script
 *     happily echoed an empty cell; matching that behaviour avoids
 *     surprising callers).
 *
 * `format_comment()` is a legacy helper from `include/functions.php`
 * which `bootstrap/app.php` already requires unconditionally — so it
 * is available from any Laravel-pipeline controller.
 */
class PreviewController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $body = (string) $request->input('body', '');

        $html = '<table width=100% border=1 cellspacing=0 cellpadding=10 align=left>'."\n"
            .'<tr><td align=left>'.format_comment($body).'<br /><br /></td></tr></table>';

        return response($html);
    }
}
