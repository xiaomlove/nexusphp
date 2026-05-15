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

### Step 5.5 — post-deletion sweep (don't skip)

Deleting `public/<page>.php` only removes the entry point. A real
legacy page in this codebase usually has 3-5 satellite references
that keep pointing at it after deletion — broken links, dead
fixtures, dead language strings. Sweep them in the same PR (or in
clearly-named follow-up PRs, one satellite at a time — see the
docleanup migration sequence #193 → #194 → #195 → #196 for the
worked precedent).

The full sweep list, in roughly the order each one bites:

1. **`lang/<locale>/lang_<page>.php`** (~19 locales × ~5-30 LOC
   each). Per-page language dictionaries the legacy template
   loaded via `get_langfile_path(['"]?<page>`. After the entry
   point is gone, nothing loads them. Confirm dead:
   ```bash
   grep -rln "get_langfile_path(['\"]\?<page>" . | grep -v vendor/
   grep -rln "lang_<page>" --include="*.php" .            \
     | grep -v vendor/ | grep -v "^./lang/.*/lang_<page>.php$"
   ```
   Only doc-comments / archaeological references should remain
   after the deletion; live `require_once` paths must be zero.

2. **`database/seeders/<…>TableSeeder.php`** — menu / panel rows
   that point at the deleted URL. The seeders are auto-generated
   2021-era files (`array (...)` syntax, extra blank lines), and
   Pint will block the seeder edit on pre-existing violations.
   Two-commit pattern:
   - Commit (a): `vendor/bin/pint database/seeders/<...>.php`,
     pure reformat, no functional change.
   - Commit (b): comment out the dead row in the new short-array
     syntax, matching the existing commented-out style above it.
   Seeders only run on fresh installs; existing installs need (3).

3. **`nexus/Install/Update.php::runExtraQueries()`** — add a
   `$this->removeMenu(['<page>.php']);` call inside a new `@since
   <next-version>` block at the end of the function. Same pattern
   as the existing `@since 1.7.12` (deletedisabled / amountupload),
   `@since 1.7.19` (freeleech), and `catmanage.php` blocks.
   `removeMenu` is idempotent (`whereIn('url', $menus)->delete()`),
   safe to keep running on every update. This is the **only** way
   existing prod installs lose their dead menu rows — the seeder
   from (2) only fires for fresh installs.

4. **`include/<page>_cli.php`** and **`docker-compose.yml` /
   `.docker/php/entrypoint.sh`** — for "scheduled job hiding behind
   HTTP" migrations (where you turned `public/<page>.php` into an
   Artisan command), check whether the same logic is also wrapped
   in a `<page>_cli.php` invoked by a dedicated docker container.
   This was the docleanup precedent: `cleanup_cli.php` + a
   `nexusphp-cleanup` container that ran every 60 seconds in
   parallel with the new `cron:autoclean` schedule. One of them
   has to die.

5. **Routes / links inside legacy templates** — `grep -rn
   "['\"]\?<page>\.php" .` should return zero hits outside the
   reference itself and any archaeological docstrings. Live
   `<a href>` / `Location: ` / `nexus_redirect(...)` references
   are bugs that 404 after the deletion.

6. **Cron / `app/Console/Kernel.php`** — if the page was being
   poked by an external cron-like mechanism (rare; usually
   ops-team's responsibility), document the new Artisan command
   in the PR description so ops can flip their runbook.

7. **Regex patterns / config arrays / allow-deny lists** — grep
   for the page name as a substring of regex alternation or
   array entries elsewhere in the codebase. The known place in
   this repo is `include/globalfunctions.php::filter_src()`,
   which carries a `$dangerScriptsPattern` alternation listing
   page-names that should be logged as `[DANGER_URL]` when
   referenced. Confirm dead:
   ```bash
   grep -rln "<page>" --include="*.php" .                    \
     | grep -vE '(/lang/|/docs/|legacy-strategy.md|migration-recipe.md|SKILL.md)'
   ```
   **Artisan-shape only.** For controller-shape migrations the
   page URL stays alive (via the `Route::any('/<page>.php', ...)`
   line you wrote in Step 3) — regex entries are still useful.
   For Artisan-shape migrations the URL becomes a hard 404, so
   any regex/config entry citing the page name becomes dead.
   docleanup is the worked precedent: PR #198 cleaned up the
   `docleanup` alternation branch in `filter_src()` post-#193.

If a page has zero hits across all seven, you're done. If a page
has hits in (1) but not (2)-(7), it's still a one-PR migration
plus a "drop dead langfiles" follow-up — that ratio is normal,
splitting reduces review burden.

### Phase 2 sub-pattern: scheduled job hiding behind HTTP

Some `public/*.php` files are not user-facing pages at all — they
are scheduled jobs that someone exposed as HTTP so a sysop could
`curl` them to force-run. Common shapes:
- The whole file is gated on a sysop-class check (`if
  ($CURUSER['class'] < UC_SYSOP) { stderr(...); }`).
- It calls one ops function (`docleanup()`, `autoclean()`, etc.)
  and exits.
- Optional `?forceall=1` query string maps to a CLI flag.

For these, the migration target is **not** a Laravel route — it
is an Artisan command. Precedent: `public/cron.php` → `cron:autoclean`
(Phase 2.1.b), `public/docleanup.php` → `cleanup:full --force-all`
(Phase 2.1.c, PR #193).

The recipe is a different shape from the controller migration:

1. New `app/Console/Commands/<Name>.php` extending
   `Illuminate\Console\Command` with a `$signature` of `<area>:<verb>
   {--flag : description}` and a `handle()` that calls the same
   legacy function the HTTP page called.
2. New `tests/Feature/Console/<Name>CommandTest.php` pinning the
   signature + flag declaration (full behavioural coverage of the
   legacy function is deferred to Phase 5).
3. **Don't register in `app/Console/Kernel::schedule()`** unless
   the operator wants it on a schedule — most ops-toggles should
   stay manual (`docker exec ... php artisan <signature>`).
4. `git rm public/<page>.php` in the same PR.
5. Run the post-deletion sweep above. For docleanup, the sweep
   uncovered 134 LOC of dead code in 3 follow-up PRs (#194 / #195 /
   #196) — bigger than the migration itself.

The CSRF middleware / route allowlist / FormRequest / view bits
from the regular Phase 2 recipe **do not apply** to Artisan
migrations — there is no HTTP surface.

### Step 6 — let the existing E2E spec pin the URL

`tests/e2e/smoke/logout.spec.ts` (or whichever file covers
`/logout.php`) should keep passing without changes — that is the
whole point of keeping the URL stable. If the spec breaks, you've
changed user-visible behaviour and the diff needs review, not a
spec edit.

## Common test pitfalls (Phase 2 lessons)

Six trip-wires we hit while migrating `thanks.php` (Phase 2.2). Read
this *before* writing your Feature test, not while debugging it.

### Pitfall 1 — `Carbon::setLocale(null)` from the Locale middleware

**Symptom:** Test fails with `Carbon\Carbon::setLocale(): Argument
#1 ($locale) must be of type string, null given` — usually before
your controller is even reached.

**Cause:** `App\Http\Middleware\Locale` reads
`$user->language->site_lang_folder` and forwards it to
`Carbon::setLocale()`. The `User::language` relation is keyed off
`users.lang`. Stock `createLegacyUser()` does not set `lang`, so
the relation comes back null and Carbon rejects the null arg.

**Fix:** Pin `lang` to the English row (`id = 6` in the seeded
`language` table) on every test user.

```php
private const ENGLISH_LANGUAGE_ID = 6;

private function createUser(array $overrides = []): User
{
    return $this->createLegacyUser(
        overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
    );
}
```

This was the single biggest false positive we chased — it looks
like a controller bug because `quote() on null` from
`last_query()` shows up in the response, but the real failure
already happened in middleware.

### Pitfall 2 — `$_SERVER['REQUEST_URI']` is empty in the test client

**Symptom:** `Undefined array key "REQUEST_URI"` from
`include/IpLogRepository::saveToCache()`, fired by the
`LogUserIp` global middleware, before your controller runs.

**Cause:** Laravel's TestResponse populates the `Request` object
but does not write to PHP superglobals. Legacy code reads
`$_SERVER` directly.

**Fix:** Seed it in `setUp()`.

```php
protected function setUp(): void
{
    parent::setUp();
    $_SERVER['REQUEST_URI'] = '/your-route.php';
    // ...
}
```

### Pitfall 3 — `get_setting()` is process-static

**Symptom:** You `seedSetting('bonus.saythanks', '1.0')` in
`setUp()`, then assert `assertEquals(11.0, $newBalance)`, and
get `12.5` back. Restarting `vendor/bin/phpunit` fixes it
locally but CI still fails.

**Cause:** `include/globalfunctions.php#get_setting()` keeps a
function-level `static` map. The first call in the PHP process
locks the values for the rest of the run; later writes to the
`settings` table do not invalidate the cache. In CI, an earlier
Feature test (e.g. `LoginFlowTest`) primes the cache with the
installer defaults *before* your `setUp()` writes its values.

**Fix:** Read what `get_setting()` will actually return, then
assert the exact arithmetic against that — not against your
seeded value.

```php
$expectedSayBonus = (float) get_setting('bonus.saythanks', self::SAYTHANKS_BONUS);
$response = $this->postJson('/thanks.php', ['id' => $torrentId]);
$this->assertEqualsWithDelta(
    10.00 + $expectedSayBonus,
    (float) NexusDB::table('users')->where('id', $thanker->id)->value('seedbonus'),
    0.05,
);
```

Phase 5 should drain the static — until then, work around it.

### Pitfall 4 — `decimal(20, 1)` columns and `assertEquals`

**Symptom:** `Failed asserting that 11.0 matches expected 11.000`
or `5.3 matches expected 5.25`.

**Cause:** `users.seedbonus` is `decimal(20, 1)` — MySQL rounds to
one decimal place on write. `5.00 + 0.25` lands on disk as `5.3`,
which `(float)` reads back as `5.3`, not `5.25`.

**Fix:** `assertEqualsWithDelta($expected, $actual, 0.05)` for any
column whose schema is `decimal(_, 1)`. Pick test values that
round cleanly (`1.0`, `2.0`) where you can.

### Pitfall 5 — `Handler::getHttpStatusCode` collapses `RuntimeException` to 200

**Symptom:** `assertStatus(405)` fails with `Expected 405, got 200`,
or `assertStatus(500)` fails with `Expected 500, got 200`. Body of
the 200 response has `ret = -1`.

**Cause:** `App\Exceptions\Handler::getHttpStatusCode()` rewrites
the HTTP status of every `\RuntimeException` (which Symfony's
`MethodNotAllowedHttpException` and most `HttpException`s extend)
to **200**, encoding the actual failure into the body as a
legacy `stderr()`-style payload. This is intentional — legacy AJAX
helpers ignore the HTTP status and only read `ret = -1` from the
body — but it makes status-based assertions unusable for any
exception path.

**Fix:** Two options.

1. Assert on the body, not the status:
   ```php
   $response->assertJson(['ret' => -1]);
   ```
2. For validation errors specifically, override `failedValidation`
   in your `FormRequest` to throw `HttpResponseException` directly
   — this short-circuits the global render pipeline, so the 422
   you wanted actually arrives:
   ```php
   protected function failedValidation(Validator $validator): void
   {
       throw new HttpResponseException(response()->json([
           'message' => $validator->errors()->first(),
           'errors' => $validator->errors()->toArray(),
       ], 422));
   }
   ```
   This is what `App\Http\Requests\Legacy\SayThanksRequest` does.

Phase 5 should fix `getHttpStatusCode` — until then, the
`ret = -1` body assertion is the canonical workaround.

### Pitfall 6 — `last_query()` and `quote() on null`

**Symptom:** `Call to a member function quote() on null` in
`Nexus\Database\NexusDB::last_query()`, raised from inside your
404/500 response. The actual failure was something else entirely.

**Cause:** When *any* exception bubbles up un-handled, Laravel
calls `App\Exceptions\Handler::report()`, which calls
`last_query()` for context. `last_query()` reaches into the active
DB connection's grammar to quote the bound parameters, and the
grammar is sometimes null in the test environment for the legacy
connection.

**Fix:** Don't try to fix `last_query()` — fix whatever exception
is actually being thrown. The `quote() on null` is a *symptom*,
not the cause. Walk up the stack to find the original throw site.
In Phase 2.2 the original was Pitfall 1 (`Carbon::setLocale(null)`).

### Schema reminder

Drop migrations like
`2025_01_18_235757_drop_torrents_table_text_column.php` quietly
remove columns. If your test fixture inserts into `torrents`,
`users`, or `peers` directly, run:

```bash
php artisan migrate:fresh --pretend
```

…against your test DB and grep the output for the columns you're
inserting. Better: `Schema::getColumnListing('torrents')` in a
throwaway test method to print the live column set.

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
      (or app/Console/Commands/ for "scheduled job hiding behind HTTP")
- [ ] FormRequest with explicit validation (where applicable)
- [ ] Route in routes/web.php keeping the *.php URL
      (skip for Artisan migrations)
- [ ] Feature test under tests/Feature/Legacy/
      (or tests/Feature/Console/ for Artisan migrations)
- [ ] Existing E2E smoke spec for the URL stays green
- [ ] public/<page>.php deleted in this PR
- [ ] scripts/legacy-loc.sh shows the watermark dropping
- [ ] No new file added under public/, include/, classes/ (CI enforces)
- [ ] Post-deletion sweep (Step 5.5):
      [ ] grep -rln 'get_langfile_path' for <page> → zero live hits
      [ ] lang/<locale>/lang_<page>.php — followup PR planned/done
      [ ] database/seeders/*TableSeeder.php — dead menu row found/commented
      [ ] nexus/Install/Update.php::runExtraQueries() — removeMenu() added
      [ ] include/<page>_cli.php + docker container — checked / dropped
      [ ] No live link / href / Location: / nexus_redirect to <page>.php
      [ ] Regex / config-array references to <page> (Artisan-shape only:
          filter_src $dangerScriptsPattern, allow-lists, etc.) — pruned
```
