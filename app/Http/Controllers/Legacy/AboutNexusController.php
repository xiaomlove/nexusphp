<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\AboutNexusService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/aboutnexus.php` (deleted in the same PR).
 *
 * Phase 3 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 3 — big user pages". Unlike Phase 2 (which mostly wrapped
 * legacy pages in a thin Laravel controller), this PR fully replaces
 * the procedural script with:
 *
 *   - a typed `AboutNexusService` that owns the DB lookups and
 *     translation-file loading,
 *   - a Blade view at `resources/views/legacy/aboutnexus.blade.php`
 *     that owns rendering,
 *   - this controller, which is only responsible for wiring data
 *     into the template and the surrounding HTML envelope.
 *
 * The original legacy flow was:
 *
 *     require "../include/bittorrent.php";
 *     dbconn();
 *     require_once(get_langfile_path());      // populates $lang_aboutnexus
 *     stdhead(PROJECTNAME);
 *     // ...emit a `<h1>` + several `begin_frame()` panels with
 *     // version, translation, stylesheet, contact info inline...
 *     stdfoot();
 *
 * The page was reachable as a guest (no `loggedinorreturn()` gate) —
 * it is linked from the global `Powered by NexusPHP` footer, so users
 * MUST be able to click it before logging in. The new controller
 * preserves that contract: no `auth.nexus` middleware on the route.
 *
 * The replacement returns a chrome-less, self-contained HTML envelope
 * wrapping the rendered body — the same precedent every Phase 2
 * controller already follows (see `RulesController` /
 * `MoreSmiliesController` / `AllAgentsController`). The legacy
 * `stdhead()` / `stdfoot()` chrome relies on a pile of globals
 * (`$Cache`, `$SITENAME`, `$CURUSER`, …) which `include/core.php`
 * only declares as locals when the include chain is `require`-d from
 * a top-level script. That is the contract a `public/*.php` entry
 * point honours and a Laravel-pipeline controller cannot — the
 * include happens inside a method scope, so the "globals" become
 * locals and `stdhead()` crashes when it dereferences `$Cache`. The
 * legacy chrome will come back in Phase 5 once it has native Blade
 * partials that don't depend on top-level-script-only globals.
 *
 * Output charset and HTML escaping are handled by Blade (`{{ }}`
 * autoescaping); the legacy script relied on the surrounding chrome
 * for charset and inlined unescaped DB values, which was both
 * brittle and a latent XSS sink if a designer or comment ever
 * contained a `<script>` payload. The Phase 3 rewrite closes that
 * gap as a side effect of moving rendering into Blade.
 */
class AboutNexusController extends Controller
{
    public function __construct(private readonly AboutNexusService $service) {}

    public function __invoke(Request $request): Response
    {
        $folder = $this->service->resolveLanguageFolder(
            $request->cookie('c_lang_folder'),
        );

        $version = $this->service->versionInfo();
        $labels = $this->service->loadTranslations($folder);
        $languages = $this->service->languages();
        $stylesheets = $this->service->stylesheets();

        $body = view('legacy.aboutnexus', [
            'labels' => $labels,
            'version' => $version,
            'languages' => $languages,
            'stylesheets' => $stylesheets,
        ])->render();

        $title = htmlspecialchars(
            $version['project_name'].' :: About',
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>{$title}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
