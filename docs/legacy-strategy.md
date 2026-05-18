# Legacy migration strategy (Strangler Fig, 5 phases)

> Reference: this is the long-form companion to the `legacy-allowed`
> label and the `Legacy freeze` CI workflow. Read
> [`CONTRIBUTING.md`](../CONTRIBUTING.md) first for the day-to-day
> rule; come back here for the migration plan.

## Size of the problem (current measurements)

Run `bash scripts/legacy-loc.sh` to refresh these numbers. As of the
freeze:

- **158 PHP files** in `public/` (`32 901` LOC) — top-level pages.
- `include/functions.php` — **6 943 LOC** (the main god-file).
- `include/globalfunctions.php` — **1 811 LOC**.
- `include/{bittorrent,bittorrent_announce,cleanup,core,functions_announce}.php` — ~2 000 LOC together.
- `classes/` — **5 files / 1 025 LOC**.
- Heaviest pages: `forums.php` 1 721, `torrents.php` 1 436, `usercp.php` 1 328, `settings.php` 973, `offers.php` 917, `catmanage.php` 885, `index.php` 862, `mybonus.php` 832, `usersearch.php` 803, `topten.php` 767, `details.php` 708, `messages.php` 701, `announce.php` 640.
- Trivial files (≤ 30 LOC, redirects / tiny AJAX endpoints): ~15. **Free wins for the first migration PRs.**

Total: **~175 files / ~45 000 LOC** of legacy. Roughly 2–3 person-years
solo or 6–9 months for a team of 3, if disciplined.

## Bad news / good news

**Bad.** Legacy is not just procedural PHP, it's a global-state
machine: `$CURUSER`, `$Cache`, `$BASEURL`, `$lang_*`, `$showXxx_main`,
`dbconn()`, `stdhead()`, `stdmsg()`, `KPS()`, `user_can()`,
`get_setting()`, `apply_filter()`. Plus a custom plugin system on top
of Laravel.

**Good.** Half the migration runway is already laid:

1. [`include/eloquent.php`](../include/eloquent.php) — 6 lines —
   bootstraps Eloquent inside legacy scripts. Already today
   `public/index.php` uses `App\Models\Torrent`, `NexusDB::select()`,
   `Carbon` next to `$Cache->get_value()`. The bridge works.
2. `mysql_*` / `sqlesc()` is steadily being replaced by
   `NexusDB::escape()` and Eloquent.
3. E2E smoke covers `public/*.php` (Phase 1–4 in `tests/e2e/smoke/`) —
   a real safety net.
4. Filament 5 already owns the admin panel.
5. Livewire 3 already powers `TorrentBrowse` (`/browse`) — there is a
   working template for migrating a page.

## The five phases

### Phase 0 — freeze (1 week, **active now**)

1. **Rule in `CONTRIBUTING.md`**: no new pages or functions in
   `public/*.php`, `include/**/*.php`, `classes/**/*.php`. Every new
   feature lands as a Laravel controller, Livewire component, or
   Filament resource.
2. **CI guard** in `.github/workflows/legacy-freeze.yml`:
   `git diff --diff-filter=A` against the legacy paths fails the
   build. Bypass via `legacy-allowed` label only.
3. **Freeze additions to `include/functions.php`**: PRs that grow the
   god-file are rejected; only deletions are accepted.

Without Phase 0 the migration is infinite — legacy grows faster than
you can shrink it.

### Phase 1 — straighten the seams (2–4 weeks)

Make every legacy page run through a Laravel route, not the legacy
front controller.

1. **`LegacyPageController` adapter:**
   ```php
   Route::get('/torrents.php', [LegacyPageController::class, 'invoke'])
       ->where('script', 'torrents');
   ```
   The controller `require`s `public/{$script}.php`, wraps globals
   in a `LegacyContext`, and returns a `Response`. Legacy code now
   runs through Laravel middleware (CSRF, rate limit, locale,
   Sentry, structured logs).
2. **Shim `$_GET` / `$_POST`** from `request()`. Lets us later
   replace `$_GET['x']` with `$request->input('x')` page by page.
3. **Shim `$CURUSER`** as `Auth::user()->toLegacyArray()`. One method
   on the `User` model.
4. **Shim `$Cache`** through `app('cache')->store()` (the default
   Laravel cache store, `redis` in production). `class_cache_redis.php`
   becomes a thin adapter that preserves the legacy `cache_value` /
   `get_value` / `delete_value` / page-cache / `getRedis` surface so
   ~440 legacy call sites keep working unchanged. `Illuminate\Cache\RedisStore`
   is byte-for-byte wire-compatible (numerics raw, everything else PHP-
   serialized, empty prefix) so cached keys survive the swap with zero
   invalidation.
5. **Shim `stdhead()` / `begin_main_frame()`** as a Blade layout
   `legacy.blade.php`. Each legacy page does `@extends('legacy')`,
   the rest stays `ob_start()`-driven.

After Phase 1, legacy code already runs **inside the Laravel
pipeline**. That alone gives you CSRF, rate limiting, real logs, and
observability — without a single page rewrite.

#### Phase 1 status — landed so far

- ✅ `App\Models\User::toLegacyArray()` — typed bridge from the
  Eloquent `User` to the `$CURUSER`-shaped array legacy pages read.
- ✅ `App\Legacy\LegacyContext` — singleton, the read-only typed API
  modern Laravel code uses to read legacy state (current user,
  settings). Hangs off the existing `nexus-web` guard, no changes to
  `include/`.
- ✅ Feature tests pin the contract (guest → null, authenticated →
  real model, legacy keys present, credential fields redacted).
- ✅ [`docs/migration-recipe.md`](migration-recipe.md) — concrete
  step-by-step recipe with `logout.php` as the worked example, plus
  a sketch of the in-between `LegacyPageController` wrap pattern.
- ✅ Phase 2.1.b — `public/cron.php` (13 LOC) deleted, replaced by
  the `cron:autoclean` Artisan command scheduled `everyMinute()` in
  `App\Console\Kernel`. The command keeps calling legacy
  `autoclean()` (so DB-side semantics are identical) but moves the
  trigger off the public HTTP surface, where any unauthenticated
  visitor could fire it. This is the pattern for `public/*.php`
  files that are really *scheduled jobs* hiding behind an HTTP
  shape — replace with Artisan + schedule, no nginx forward, no
  Laravel route.
- ✅ `App\Http\Controllers\Legacy\LegacyPageController` — invokable
  controller that wraps a single allowlisted legacy `public/*.php`
  page in the Laravel pipeline. Ships with an intentionally empty
  `ALLOWED` constant; each entry needs a manual process-isolation
  review (legacy code that calls `die()` mid-render kills the FPM
  worker) and is added by a follow-up PR. Populates
  `$GLOBALS['CURUSER']` from `LegacyContext`, buffers the legacy
  `echo` output via `ob_start()` / `ob_get_clean()`, and returns
  the result as a Symfony `Response` so Laravel middleware can
  still observe and rewrite headers/body.
- ✅ `App\Legacy\LegacyChrome` + `resources/views/layouts/legacy.blade.php`
  — Blade layout shim for `stdhead()` / `stdfoot()`. Captures the
  legacy site chrome as plain strings so a modern Laravel
  controller can render its body inside the legacy header/footer
  without `require`-ing `include/bittorrent.php` at the call site.
  Chrome function names are overridable via `config('legacy.chrome')`
  so tests don't need to redeclare globals.
- ✅ `classes/class_cache_redis.php` collapse onto `app('cache')->store()`.
  The hand-rolled phpredis client / `serialize` / `unserialize` are
  gone; every Redis hit now goes through `Illuminate\Cache\Repository`,
  which uses the same wire format. The default store (not `Cache::store('redis')`)
  is used so Feature tests that swap `cache.default` to `array` run
  without a live Redis. In the legacy fastcgi path (where Laravel's
  full Application is not booted) the class lazily registers `redis`
  / `config` / `cache` on `Container::getInstance()`, mirroring
  `Nexus\Nexus::getQueueManager()`. Public API (`cache_value`,
  `get_value`, `delete_value`, `new_page` / `get_page` / `cache_page`,
  `add_row` / `next_row` / `break_loop`, `lock` / `unlock`, `getRedis`,
  metadata getters) is unchanged.
- ✅ `public/adredir.php` (40 LOC) → `AdRedirectController`. The
  rewrite also closes a long-standing open-redirect bug: the legacy
  script passed `?url=` straight to `header("Location: ...")` with
  no whitelist, so an attacker who knew any valid `advertisements.id`
  could land `adredir.php?id=<known>&url=<evil>` and have the
  trusted host redirect to `<evil>`. The controller now extracts
  the SYSOP-embedded URLs from `advertisements.code` (written
  exclusively by `public/admanage.php`) with a regex matching the
  `<a href="adredir.php?id=ID&amp;url=URL_RAWURLENCODED">` shape
  `admanage.php` writes for text/image ad types, and only accepts
  `?url=` values that decode to one of those whitelisted URLs.
  Production ads keep working without any data migration — the
  redirect target is the same URL the legacy script would have
  redirected to.

Phase 1 infrastructure is complete; future work belongs to Phase 2
(per-file `public/*.php` migrations) and beyond.

The rest of the strategy doc continues to apply unchanged.

### Phase 2 — strip the trivials (3–4 weeks)

Take every `public/*.php` ≤ 100 LOC and rewrite it as a Laravel
controller. About 40 files, average half a day each.

For each file:

1. New controller in `app/Http/Controllers/Legacy/`.
2. New `FormRequest` with explicit validation.
3. Route in `routes/web.php` keeping the **old** URL (`Route::post('/thanks.php', …)`).
4. The existing E2E smoke spec for that URL stays green.
5. **Delete the old file in the same PR.**

Net: −3 000 LOC legacy, +purpose-built tests, +validation.

### Phase 3 — big user pages (3–6 months)

In descending order of value:

1. `index.php` (862) → Livewire `Home` (news, shoutbox, latest torrents).
2. `details.php` (708) → `TorrentDetails` Livewire.
3. `torrents.php` (1 436) → extend the existing `TorrentBrowse` Livewire.
4. `usercp.php` (1 328) → Filament-style page split into tabs (`UserCp\Profile`, `UserCp\Security`, `UserCp\Notifications`).
5. `settings.php` (973), `mybonus.php` (832), `topten.php` (767), `userdetails.php` (610) — analogous.

Per-page rule:

- Migration = new Laravel route + Livewire/Blade + delete legacy in
  the **same PR**.
- Don't keep "double truth" longer than a week — behavior drifts.
- A 2–3 day feature-flag canary (`?legacy=1`) is fine for rollback.

#### Phase 3.x: forums.php flip

The Livewire replacements (`ForumIndex` / `ForumView` / `TopicView` /
`NewTopicForm` / `EditPostForm`) were merged in earlier PRs but
`public/forums.php` (1 734 LOC) kept owning the canonical URL.

Strangler flip applied at the head of `public/forums.php`:

| Legacy URL | Flipped to | Livewire component |
|---|---|---|
| `/forums.php` | `/forum` | `ForumIndex` |
| `/forums.php?action=viewforum&forumid=N` | `/forum/N` | `ForumView` |
| `/forums.php?action=newtopic&forumid=N` | `/forum/N/new` | `NewTopicForm` |
| `/forums.php?action=viewtopic&topicid=N` | `/forum/topic/N` → `/forum/{forumid}/topic/N` | `TopicView` |
| `/forums.php?action=reply&topicid=N` | `/forum/topic/N?compose=reply` → `/forum/{forumid}/topic/N#reply` | `TopicView` + inline `ReplyForm` |
| `/forums.php?action=quotepost&postid=N` | `/forum/post/N?compose=quote` → `/forum/{forumid}/topic/{topicid}?quote=N#reply` | `TopicView` + inline `ReplyForm` (prefilled) |
| `/forums.php?action=editpost&postid=N` | `/forum/post/N?compose=edit` → `/forum/{forumid}/topic/{topicid}?edit=N#post-N` | `TopicView` + inline `EditPostForm` (pre-opened) |
| `/forums.php?action=viewunread` | `/forum/unread` (cursor `?beforepostid=N` preserved) | `ForumUnread` |
| `/forums.php?action=search` | `/forum/search` (`?keywords=...` preserved) | `ForumSearch` |
| `/forums.php?action=deletetopic&topicid=N` | `/forum/topic/N` → `/forum/{forumid}/topic/N` | `TopicView` + Delete-topic `wire:confirm` button |

`viewtopic` is a two-hop redirect because `TopicView` needs both
`forumid` and `topicid`, but the legacy URL only carries `topicid`.
We deliberately do not run a DB query before Laravel boots (the
legacy entry point would have to parse `.env` or load `dbconn.php`
to learn the credentials), so the first hop targets
`/forum/topic/{topic}` — a Laravel route that performs the
`topics → forumid` lookup and re-redirects to the canonical URL.
The second hop is acceptable because the legacy URL is hit only by
bookmarks / RSS / cross-site links; first-party navigation already
uses the canonical URL directly.

Escape hatches that stay on legacy:

- `?legacy=1` — explicit canary opt-out.
- POST `?action=post` — the form-submit handler. The Livewire
  ReplyForm / EditPostForm / NewTopicForm components submit via
  Livewire HMR, not via a form POST to this endpoint, so a redirect
  cannot help; the legacy handler stays until no template path
  generates a `forums.php?action=post` POST any more.
- POST admin actions (`movetopic`, `setlocked`, `hltopic`,
  `setsticky`) — the legacy moderation forms POST to these handlers
  and only reach them via `?legacy=1` canary opt-out (the modern
  `TopicView` and `ForumView` Livewire components call
  `ForumPostService::{moveTopic,setLocked,setSticky,setHlColor}`
  directly via `wire:click`). The legacy handlers stay as escape
  hatches until the canary phase ends.
- `?action=deletepost` — the legacy delete-single-post handler.
  Modern callers use `TopicView::deletePost()` (`wire:click`); the
  legacy GET stays as an escape hatch.

- `?catchup=1` — the legacy GET form of the catch-up action.
  Modern callers use the Livewire `ForumUnread::catchUp()` method
  (wired to the "Catch up" button on `/forum/unread`), which
  performs the same three steps as the legacy handler (delete
  `readposts` for the user, bump `users.last_catchup` to
  `max(posts.id)`, forget the legacy
  `user_<id>_last_read_post_list` cache key). The legacy GET stays
  as an escape hatch for direct hits (e.g. the legacy forum index
  footer link) until the legacy index is also retired.

Contract is covered by `tests/e2e/behavior/forums-flip.spec.ts`.
After every escape hatch has been retired the file goes to a single
`require '/forum';` shim and then to `git rm`.

#### Phase 3.x: torrents.php flip

The Livewire `App\Livewire\TorrentBrowse` was merged in the Modern UI
A2 series of PRs. `public/torrents.php` (1 494 LOC) flipped its
default to a 302 redirect to `/browse` from the start, but kept a
small number of escape hatches reachable verbatim. Those escape
hatches are tracked here so the file can eventually go to a single
`require '/browse';` shim and then to `git rm`.

Strangler flip applied at the head of `public/torrents.php`:

| Legacy URL | Flipped to | Livewire component |
|---|---|---|
| `/torrents.php` | `/browse` | `TorrentBrowse` |
| `/torrents.php?cat=N` | `/browse?category=N` | `TorrentBrowse` |
| `/torrents.php?incldead=N` | `/browse?incldead=dead\|all` (0 → drop) | `TorrentBrowse` |
| `/torrents.php?spstate=N` | `/browse?spstate=N` (0 → drop) | `TorrentBrowse` |
| `/torrents.php?bookmarks=1` | `/browse?inclbookmarked=only` | `TorrentBrowse` (`BOOKMARK_ONLY` mode) |

All other query params (`search`, `sort`, `tag_id`, `mode`, etc.)
are forwarded verbatim; `TorrentBrowse` hydrates state from `#[Url]`
attributes and ignores keys it does not know.

Escape hatches that stay on legacy:

- `?legacy=1` — explicit canary opt-out, the rollback flag.
- `?ajax=1` — the search-as-you-type fragment endpoint (PR #59).
  The legacy page emits a tiny inline JS handler that builds URLs
  as `?ajax=1&search=…` and `innerHTML`s the result fragment into
  `#torrents-results`. The endpoint returns a results FRAGMENT (no
  `<html>`/`<head>` wrapper). Redirecting would break the swap, so
  this stays until the inline JS is removed (which only happens
  when the legacy page itself is retired — modern UI uses Livewire
  live filters and never builds `?ajax=1` URLs).

Contract is covered by `tests/e2e/behavior/torrents-flip.spec.ts`.

#### Phase 3.x: details.php flip

The Livewire `App\Livewire\TorrentDetail` was merged in the Modern UI
A3 series of PRs but `public/details.php` (714 LOC) kept owning the
canonical URL.

Strangler flip applied at the head of `public/details.php`:

| Legacy URL | Flipped to | Livewire component |
|---|---|---|
| `/details.php?id=N` | `/torrent/N` | `TorrentDetail` |

Single-hop redirect — `TorrentDetail` takes only `{id}` from the
route, so there is no second-hop resolver needed (unlike forums'
`viewtopic` / `quotepost` / `editpost`). Extra query params beyond
`id` (and the escape hatches below) are forwarded verbatim;
`TorrentDetail` ignores keys it does not know, so bookmark-with-extra
URLs survive the first hop without data loss.

Escape hatches that stay on legacy:

- `?legacy=1` — explicit canary opt-out, the rollback flag.
- `?cmtpage=N` — comments pagination. The comments listing has not
  been migrated to Livewire yet (the existing `TorrentComments`
  Livewire wires the comments tab on the modern page but does not
  yet own the canonical comments URL with pagination), so paged
  URLs must stay on legacy.
- `?uploaded` / `?edited` / `?existed` (+ optional `?returnto`) —
  post-write success banners after the legacy upload / edit flows.
  Modern UI has no equivalent banner; legacy keeps owning them
  until the upload / edit flows themselves move to Livewire.
- non-GET requests — the inline action POSTs still served by this
  file (e.g. `?subtitleupload`).

Retired escape hatches (now flip cleanly):

- `?hit=1` — the view-counter side effect was hoisted into
  `App\Livewire\TorrentDetail::mount()`. First-party "open from
  listing" links from `public/index.php`, `public/userhistory.php`,
  `public/myhr.php` and the legacy `details.php` "related torrents"
  block now flip to `/torrent/{id}?hit=1` and the Livewire component
  increments `torrents.views` directly.
- `?dllist=1` — the legacy auto-open peer-list dialog hint is a
  no-op on the Modern UI (the peer list is rendered server-side as
  part of the page). Listing links carrying `#seeders` / `#leechers`
  fragments still resolve correctly: the Blade view exposes matching
  `id="seeders"` / `id="leechers"` anchors on the peer sections, so
  the browser scroll-to-fragment behaviour survives the 302.

Contract is covered by `tests/e2e/behavior/details-flip.spec.ts`.
After every escape hatch has been retired the file is deleted in a
single follow-up `git rm public/details.php` PR.

### Phase 4 — hot path (announce / scrape) — separately (1–2 months)

**Do NOT migrate `announce.php` to a Laravel controller.** Booting
Laravel costs 30–50 ms per cold request; an announce must be < 5 ms.

Options, weakest to strongest:

1. **Minimum.** Leave `announce.php` as a single file; stop pulling
   in `include/functions.php` (6 943 LOC for ~50 functions).
   Extract the 50 needed functions into `nexus/Tracker/`, leave
   `announce.php` as the lone consumer of `bittorrent_announce.php`.
2. **Middle.** Laravel Octane + a dedicated `/announce` route. The
   bootstrap pays once, then Octane workers serve in nanoseconds.
3. **Best.** Split the tracker into a separate microservice — Go
   (`chihaya`, `crystal`) or Rust (`opentracker-rs`). PHP serves
   pages, the tracker is its own process. The right shape for any
   PT with > 50 k peers.

Regardless of option:

- Drop `ORDER BY RAND()` for peer sampling — the single largest
  win on the hot path.
- Move peer lists to a Redis ZSET (TTL = `2 × announce_interval`).
- Update `seeders` / `leechers` only in Redis; reconcile to MySQL
  every N minutes via the scheduler.

### Phase 5 — drain `include/functions.php` (3–6 months, background)

6 943 LOC ≈ ~150 functions. Approach:

1. Bucket each function into a domain: `Bonus`, `Auth`, `Torrent`,
   `User`, `Format`, `Mail`, …
2. Create `app/Services/{Domain}Service.php` per domain.
3. Convert the legacy function into a **proxy**:
   ```php
   function get_user_class() {
       return app(\App\Services\UserService::class)->getCurrentUserClass();
   }
   ```
4. Once every call site uses the service, delete the proxy.
5. This work runs **in parallel with Phase 3** — every page you
   migrate naturally drains 5–10 functions.

End state: `include/functions.php` shrinks to a few hundred lines of
proxies, then disappears.

## Picking the first page

Ideal candidate for the first migration PR:

- ≤ 100 LOC.
- No `$Cache->new_page()` / `$Cache->add_part()` (HTML caching is its
  own boss fight).
- Has an existing Phase 4 E2E smoke spec.
- Not on the announce hot path.

Candidates from `wc -l`:

| File | LOC | Why |
|---|---|---|
| `logout.php` | 8 | Pure redirect, `Auth::logout()` in 5 minutes. |
| `cron.php` | 13 | Likely a thin wrapper around the scheduler — fold into `Console\Kernel::schedule()`. |
| `contactstaff.php` | 14 | Contact form, perfect "first full pattern" page. |
| `image.php` | 22 | Avatar proxy → `ImageController`. |
| `thanks.php` | 25 | Single POST handler. |
| `getextinfoajax.php` | 27 | Single AJAX endpoint. |

`contactstaff.php` or `thanks.php` give the full pattern (route +
FormRequest + controller + view + test) on the smallest surface.

## What NOT to do

1. **No "big rewrite branch"** running for 3 months. It will not
   merge — guaranteed.
2. **No automated rewriter** via regex/AST. Each page is its own
   story; manual is faster overall.
3. **No "double truth"** longer than a week. Once a page is migrated,
   its legacy file is deleted in the same PR.
4. **Don't touch `include/functions.php` before Phase 5.** It is
   imported by everything; any change is a regression vector.
5. **Don't move the tracker to a Laravel controller.** Performance
   deal-breaker.

## Progress metric

`bash scripts/legacy-loc.sh` is the watermark. The
`Legacy freeze` workflow prints it to every CI run's step summary.

Targets:

| Quarter | Legacy LOC delta |
|---|---|
| Q1 | −20% |
| Q2 | −40% |
| Q3 | −60% |
| Q4 | −80% |

## In one paragraph

Set the rule "new code goes only into Laravel"; wrap legacy pages in
a Laravel route with shims for the globals; start with trivial files
(≤ 100 LOC) to get the process in muscle memory; migrate one page at
a time to Livewire/Blade and delete the legacy file in the same PR;
in parallel drain `include/functions.php` through service-proxy
adapters; and keep the tracker (`announce.php`) on a separate path —
Octane or a microservice, not a Laravel controller.
