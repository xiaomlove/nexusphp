<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 1 seam: invokable controller that wraps a single legacy
 * `public/<page>.php` script in the Laravel HTTP pipeline.
 *
 * Why this exists
 * ----------------
 * `routes/web.php` historically routed nothing — every legacy URL was
 * served directly by the procedural `public/<page>.php` files which
 * never went through Laravel middleware (no CSRF, no rate limit, no
 * structured logs, no Sentry). The Strangler-Fig migration plan
 * (`docs/legacy-strategy.md`) calls for moving those URLs through a
 * thin Laravel adapter first — this controller — so that the Laravel
 * pipeline benefits land independently of any rewrite work.
 *
 * What it does
 * ------------
 * 1. Hard rejects (`404`) any `$page` not in {@see ALLOWED}. The
 *    allowlist is intentionally tiny and grows one PR at a time —
 *    each entry needs a process-isolation review (legacy code that
 *    calls `die()` mid-render kills the FPM worker).
 * 2. Populates `$GLOBALS['CURUSER']` from {@see LegacyContext} so the
 *    legacy script's contract is intact without round-tripping through
 *    `dbconn()` / `userlogin()` (which would re-bootstrap globals,
 *    open a second DB connection, and possibly emit early
 *    `header()` / `die()` calls on banned IPs).
 * 3. Buffers the legacy `echo`-driven output and returns it as a
 *    Symfony `Response`, so Laravel middleware can still observe and
 *    rewrite headers/body on the way out.
 *
 * What it does NOT do
 * -------------------
 * - It does not auto-route every `*.php` URL — `routes/web.php` must
 *   add a per-page route for each entry in `ALLOWED`.
 * - It does not protect against `die()` / `exit` inside the legacy
 *   include. A page that calls those mid-render terminates the FPM
 *   worker; never add such a page to the allowlist without first
 *   converting its terminations to `nexus_redirect()` /
 *   `throw new \HttpResponseException(...)` style returns.
 * - It does not interpose on the legacy `dbconn()`. Pages added here
 *   are still expected to bootstrap `include/bittorrent.php` (or
 *   equivalent) themselves.
 *
 * @see docs/legacy-strategy.md   — the 5-phase plan this is part of
 * @see docs/migration-recipe.md  — the per-page recipe; this is the "wrap" pattern
 */
class LegacyPageController extends Controller
{
    /**
     * Pages that have been verified safe to wrap.
     *
     * Each entry is the basename (without `.php`) of a file under
     * `public/`. Adding a page here unlocks routing it through this
     * controller; never add a page that calls `die()` / `exit` mid
     * render — see class doc.
     *
     * Phase 1 ships this empty on purpose. Phase 2 PRs that want to
     * wrap a page (rather than rewrite it) extend this list.
     *
     * @var list<string>
     */
    protected const ALLOWED = [];

    public function __construct(
        private readonly LegacyContext $context,
        private readonly string $legacyRoot,
    ) {}

    public function __invoke(Request $request, string $page): Response
    {
        if (! in_array($page, $this->allowedPages(), true)) {
            abort(404);
        }

        $file = $this->legacyRoot.DIRECTORY_SEPARATOR.$page.'.php';
        if (! is_file($file)) {
            abort(404);
        }

        $GLOBALS['CURUSER'] = $this->context->userAsLegacyArray();

        ob_start();
        try {
            require $file;
        } finally {
            $body = (string) ob_get_clean();
        }

        return response($body);
    }

    /**
     * Subclass hook so tests can swap the allowlist without mutating
     * the production constant.
     *
     * @return list<string>
     */
    protected function allowedPages(): array
    {
        return static::ALLOWED;
    }
}
