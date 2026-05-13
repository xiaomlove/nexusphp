<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\AboutNexusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
 *     into the template and the legacy chrome.
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
 * The replacement uses `view('layouts.legacy', ...)` (rather than the
 * chrome-less envelope picked by every Phase 2 controller so far) to
 * keep visual parity with the rest of the site for guest visitors —
 * an `aboutnexus.php` page without site header/footer would look
 * broken when reached from the footer link. Phase 5 will replace the
 * legacy chrome with native Blade partials, at which point only
 * `resources/views/layouts/legacy.blade.php` changes; this controller
 * and its view stay the same.
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

    public function __invoke(Request $request): View
    {
        $folder = $this->service->resolveLanguageFolder(
            $request->cookie('c_lang_folder'),
        );

        $version = $this->service->versionInfo();
        $labels = $this->service->loadTranslations($folder);
        $languages = $this->service->languages();
        $stylesheets = $this->service->stylesheets();

        // Pre-render the body so `layouts.legacy` (which expects a
        // string `content` slot) can wrap it without recursing into
        // another Blade compile inside the chrome layout.
        $body = view('legacy.aboutnexus', [
            'labels' => $labels,
            'version' => $version,
            'languages' => $languages,
            'stylesheets' => $stylesheets,
        ])->render();

        return view('layouts.legacy', [
            'title' => $version['project_name'],
            'content' => $body,
        ]);
    }
}
