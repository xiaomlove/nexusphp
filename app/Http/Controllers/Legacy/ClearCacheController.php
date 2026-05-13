<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Replacement for `public/clearcache.php` (deleted in the same PR).
 *
 * Phase 2 batch #4 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_MODERATOR` → `stderr("Error", "Permission denied.")`.
 *   3. GET → render a form (cachename + multilang checkbox) wrapped
 *      in `stdhead('Clear cache')` / `stdfoot()`.
 *   4. POST with empty `cachename` → `stderr("Error", "You must fill
 *      in cache name.")`.
 *   5. POST with non-empty `cachename` →
 *      `$Cache->delete_value($cachename, $multilang === 'yes')`,
 *      then redisplay the form with a "Cache cleared" notice.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_MODERATOR` → `abort(403)`
 *     (same hardening as `AllAgentsController` / `TakeUpdateController`
 *     — legacy `stderr()` rendered HTTP 200, which is the wrong status
 *     and trips up the JSON-aware exception handler).
 *   - POST without `cachename` → re-render the form with an error
 *     message (no separate flash session needed — the controller
 *     just inlines the message).
 *   - POST with `cachename` → `Cache::forget($cachename)` (and the
 *     `<lang>_<cachename>` variants when `multilang=yes`), then
 *     re-render the form with the "Cache cleared" confirmation.
 *   - The HTML envelope is chrome-less and self-contained, matching
 *     the `MoreSmiliesController` precedent.
 *
 * The Laravel cache facade and the legacy `$Cache->delete_value()`
 * share the same Redis connection (see `config/cache.php` —
 * `'prefix' => ''`) and the same `phpredis` extension, so the
 * resulting Redis `DEL` is identical to what the legacy code emits.
 * This mirrors the bridge logic already in `NexusDB::cache_del()`.
 */
class ClearCacheController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        $cleared = false;
        $error = null;
        $cachename = '';
        $multilang = false;

        if ($request->isMethod('POST')) {
            $cachename = trim((string) $request->input('cachename', ''));
            $multilang = $request->input('multilang') === 'yes';
            if ($cachename === '') {
                $error = 'You must fill in cache name.';
            } else {
                Cache::forget($cachename);
                if ($multilang) {
                    foreach (get_langfolder_list() as $folder) {
                        Cache::forget($folder.'_'.$cachename);
                    }
                }
                $cleared = true;
            }
        }

        $body = $this->renderForm($cachename, $multilang, $cleared, $error);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Clear cache</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Render the cache-clearing form (and any inline status / error
     * notice). The markup matches the legacy script's output.
     */
    private function renderForm(string $cachename, bool $multilang, bool $cleared, ?string $error): string
    {
        $statusBlock = '';
        if ($cleared) {
            $statusBlock .= '<p align="center"><font class="striking">Cache cleared</font></p>'."\n";
        }
        if ($error !== null) {
            $statusBlock .= sprintf(
                '<p align="center"><font class="striking">%s</font></p>'."\n",
                htmlspecialchars($error),
            );
        }

        $cachenameEsc = htmlspecialchars($cachename);
        $multilangChecked = $multilang ? ' checked' : '';

        return '<h1>Clear cache</h1>'."\n"
            .$statusBlock
            .'<form method="post" action="clearcache.php">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="rowhead">Cache name</td><td>'
            .'<input type="text" name="cachename" size="40" value="'.$cachenameEsc.'">'
            .'</td></tr>'."\n"
            .'<tr><td class="rowhead">Multi languages</td><td>'
            .'<input type="checkbox" name="multilang" value="yes"'.$multilangChecked.'>Yes'
            .'</td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" value="Okay" class="btn">'
            .'</td></tr>'."\n"
            .'</table>'."\n"
            .'</form>'."\n";
    }
}
