# Legacy → Laravel migration recipe

A concrete, step-by-step recipe for taking one procedural-PHP page out
of `public/` and replacing it with a Laravel route + controller (+
Livewire component, where it makes sense).

This is the day-to-day companion to
[`legacy-strategy.md`](legacy-strategy.md). The strategy doc explains
*why* and *in what order*; this doc explains *how*.

## When to use this recipe

Use it for **Phase 2** files: legacy pages ≤ 100 LOC that are roughly
"one HTTP request → one DB write → redirect / small response", and
that already have an E2E smoke spec covering them.

Use the same recipe (with more steps for the views) for the bigger
**Phase 3** pages — the only difference is that you build a Livewire
component instead of a thin controller.

For `announce.php` / `scrape.php` (Phase 4): **do not follow this
recipe**. Booting Laravel for an announce is a non-starter. See
`legacy-strategy.md` § "Phase 4".

## The seam (Phase 1, already in place)

Two pieces of infrastructure that the steps below assume exist:

1. `App\Legacy\LegacyContext` — the typed read-only API for legacy
   state (current user, settings). New Laravel code uses this instead
   of touching `$CURUSER`, `$Cache`, `get_setting()` directly.
2. `App\Models\User::toLegacyArray()` — converts a `User` model to a
   `$CURUSER`-shaped array. Useful when you have a Laravel route that
   has to populate `$GLOBALS['CURUSER']` before requiring a legacy
   include (a "wrap" rather than a full migration).

Both are typed, both have unit/feature tests, neither touches
`include/`. New migration PRs build on top of them.

## Worked example: `public/logout.php` → `LogoutController`

The smallest legacy page in the tree (8 LOC). Walking through it
hits every step you'll repeat for bigger pages.

### Step 0 — pick the page

```bash
$ wc -l public/*.php | sort -n | head -10
   8 public/logout.php          # ← good first candidate
  13 public/cron.php
  14 public/contactstaff.php
  22 public/image.php
  25 public/thanks.php
```

Criteria, in priority order:

1. ≤ 100 LOC.
2. Already covered by an E2E spec under `tests/e2e/smoke/`.
3. No `$Cache->new_page()` / `add_part()` / `end_page()` (HTML cache
   is its own boss fight — leave those for Phase 3).
4. Not on the announce hot path.
5. Doesn't write through `stdhead()` / `stdfoot()` (no big page chrome
   to recreate).

### Step 1 — read the legacy file

`public/logout.php`:

```php
<?php
require_once("../include/bittorrent.php");
dbconn();
logoutcookie();
nexus_redirect("/");
```

What it does: bootstraps legacy includes, opens a DB connection,
clears the auth cookie, redirects to `/`.

What it depends on: `logoutcookie()` (sets a `Set-Cookie:
c_secure_pass=; expires=…` header), `nexus_redirect()` (a wrapper
around `header('Location: …'); exit`).

### Step 2 — write the Laravel controller

```php
// app/Http/Controllers/Legacy/LogoutController.php
namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Drop the legacy auth cookie (`c_secure_pass`) the same way
        // `logoutcookie()` does in include/functions.php.
        $response = redirect('/');
        $response->withCookie(cookie('c_secure_pass', '', -1, '/'));

        return $response;
    }
}
```

Three things to notice:

- Single-action invokable controller. We deliberately do not stuff
  more than one URL into one controller — easier to delete later.
- Lives in `App\Http\Controllers\Legacy\` so it's obvious this is a
  migration target, not the long-term home. Phase 5 will rename
  these out of `Legacy\` once their dependencies on the legacy
  cookie shape are gone.
- Uses Laravel's cookie helper, not the legacy `setcookie()` global.

### Step 3 — wire the route

`routes/web.php`:

```php
use App\Http\Controllers\Legacy\LogoutController;

Route::any('/logout.php', LogoutController::class)
    ->name('legacy.logout');
```

Note the URL stays `/logout.php` — that is how every existing link in
templates / emails reaches it. Changing the URL is its own PR.

### Step 4 — write a Feature test

```php
// tests/Feature/Legacy/LogoutControllerTest.php
namespace Tests\Feature\Legacy;

use Tests\TestCase;

class LogoutControllerTest extends TestCase
{
    public function test_logout_clears_legacy_cookie_and_redirects(): void
    {
        $response = $this->get('/logout.php');

        $response->assertRedirect('/');
        $cookie = $response->getCookie('c_secure_pass');
        $this->assertNotNull($cookie);
        $this->assertSame('', $cookie->getValue());
        $this->assertLessThan(time(), $cookie->getExpiresTime());
    }
}
```

This is a "real" Laravel test — no PHP built-in server, no Guzzle,
no separate process. You get this for free because the new logout
goes through Laravel's pipeline, not legacy's `die()`-driven flow.

### Step 5 — delete the old file in the same PR

```bash
$ git rm public/logout.php
$ bash scripts/legacy-loc.sh
public/*.php (top-level)         157 files    32893 LOC   # was 158 / 32 901
```

This is the one rule that matters most: **migration = new code +
delete legacy in the same PR**. Two separate PRs ("add new", then
"remove old") almost always leaves you with both versions live for
weeks while the team forgets which is canonical.

The CI `Legacy freeze` workflow notices the deletion and prints the
new watermark. Expect Q1 to land at roughly −20% legacy LOC.

### Step 6 — let the existing E2E spec pin the URL

`tests/e2e/smoke/logout.spec.ts` (or whichever file covers
`/logout.php`) should keep passing without changes — that is the
whole point of keeping the URL stable. If the spec breaks, you've
changed user-visible behaviour and the diff needs review, not a
spec edit.

## Recipe for an "in-between" wrap (no rewrite yet)

Sometimes you want the Laravel pipeline (rate-limit middleware,
Sentry, structured logs) without rewriting the page yet. The pattern
is the same shape, but the controller proxies into the legacy
include:

```php
// app/Http/Controllers/Legacy/LegacyPageController.php (sketch)
namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyPageController extends Controller
{
    /**
     * Allowlist of legacy pages we have explicitly verified to be
     * safe to wrap in a Laravel route. Pages that `die()` mid-render
     * cannot be added here — they kill the Laravel process.
     */
    private const ALLOWED = [
        // Add pages here as you verify them, one at a time.
    ];

    public function __invoke(Request $request, string $page, LegacyContext $ctx): Response
    {
        abort_unless(in_array($page, self::ALLOWED, true), 404);

        $file = base_path("public/{$page}.php");
        abort_unless(is_file($file), 404);

        // Populate the global $CURUSER the legacy file expects.
        $GLOBALS['CURUSER'] = $ctx->userAsLegacyArray();

        ob_start();
        try {
            require $file;
        } finally {
            $body = (string) ob_get_clean();
        }

        return response($body);
    }
}
```

This is Phase 1 territory. We have **not** wired this controller yet
— picking the first allowlisted page is its own PR with its own
process-isolation review (legacy code that calls `die()` will kill
the web worker).

## Picking the next pages

Order suggested by `wc -l` + Phase 4 E2E coverage:

| File | LOC | Why |
|---|---|---|
| `logout.php` | 8 | Pure redirect — the worked example above. |
| `cron.php` | 13 | Likely fold into `Console\Kernel::schedule()`. |
| `contactstaff.php` | 14 | Contact form, the canonical "first full pattern" page. |
| `image.php` | 22 | Avatar proxy → `ImageController`. |
| `thanks.php` | 25 | Single POST handler. |
| `getextinfoajax.php` | 27 | Single AJAX endpoint. |

`contactstaff.php` and `thanks.php` are the best second migrations:
both exercise route + FormRequest + controller + view + test on a
small surface, so the team locks the recipe into muscle memory
before tackling 800-LOC pages.

## Checklist — copy this into your PR description

```
- [ ] New Laravel controller in app/Http/Controllers/Legacy/
- [ ] FormRequest with explicit validation (where applicable)
- [ ] Route in routes/web.php keeping the *.php URL
- [ ] Feature test under tests/Feature/Legacy/
- [ ] Existing E2E smoke spec for the URL stays green
- [ ] public/<page>.php deleted in this PR
- [ ] scripts/legacy-loc.sh shows the watermark dropping
- [ ] No new file added under public/, include/, classes/ (CI enforces)
```
