<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Replacement for `public/formats.php` (deleted in the same PR).
 *
 * The legacy script was a pure static guide on common file
 * extensions (RAR / ZIP / DivX / VCD / etc.) — `dbconn() +
 * loggedinorreturn() + stdhead() + 200 lines of inline HTML`.
 * The body has zero dynamic content (no DB queries, no language
 * file lookups, no permission-dependent branching, no settings
 * read), so we render the static HTML verbatim from a Blade
 * template (`resources/views/legacy/formats.blade.php`) inside
 * a chrome-less HTML envelope.
 *
 * Phase 5 will retranslate this content (lang_formats.php for
 * each locale, or move to a Markdown file under `_doc/`); at
 * that point this controller will be replaced. Until then the
 * contract is "render exactly what the legacy script rendered,
 * minus the legacy chrome".
 *
 * Mirrors `MoreSmiliesController` / `RulesController` for the
 * chrome-less envelope shape.
 */
class FormatsController extends Controller
{
    public function __invoke(): Response
    {
        $body = view('legacy.formats')->render();

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>NexusPHP :: Downloaded Files</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
