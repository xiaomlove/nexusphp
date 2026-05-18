<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/useragreement.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md` § "Phase 2"
 * and `docs/migration-recipe.md`. The legacy page was a guest-accessible
 * static legal text wrapped in `stdhead`/`begin_main_frame`/`begin_frame`
 * site chrome. It interpolated three values into the body:
 *
 *   - `$SITENAME`  (`basic.SITENAME` — hostname-style label)
 *   - `$BASEURL`   (`basic.BASEURL` — hostname only, from `include/config.php`)
 *   - `$baseUrl`   (`getSchemeAndHttpHost()` — full URL with scheme)
 *
 * The replacement keeps the same URL (`/useragreement.php`) and the same
 * guest-accessible contract (no auth gate — the page is linked from
 * `lang/<locale>/lang_faq.php`'s `text_welcome_content_two`). The Blade
 * view at `resources/views/legacy/useragreement.blade.php` owns the
 * static body; this controller resolves the three placeholders, renders
 * the view, and wraps it in a chrome-less HTML envelope. The legacy
 * `stdhead()` / `stdfoot()` chrome will come back in Phase 5 once it has
 * native Blade partials — same precedent as `AboutNexusController`,
 * `RulesController`, `MoreSmiliesController`, `AllAgentsController`.
 *
 * Output escaping is handled by Blade `{{ }}` autoescaping — the legacy
 * script emitted these values raw, which was a latent XSS sink if
 * `basic.SITENAME` or `basic.BASEURL` ever contained `<script>`. The
 * Phase 2 rewrite closes that gap as a side effect of moving rendering
 * into Blade.
 */
class UserAgreementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $sitename = (string) (Setting::getByName('basic.SITENAME') ?? 'NexusPHP');
        $baseurl = (string) (Setting::getByName('basic.BASEURL') ?? $request->getHttpHost());
        $baseurlFull = (string) $request->getSchemeAndHttpHost();

        $body = view('legacy.useragreement', [
            'sitename' => $sitename,
            'baseurl' => $baseurl,
            'baseurlFull' => $baseurlFull,
        ])->render();

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>NexusPHP :: User Agreement</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
