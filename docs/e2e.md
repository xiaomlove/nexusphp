# NexusPHP end-to-end test suite

This document is the developer-facing entry point for the Playwright
E2E suite under `tests/e2e/`. It explains what the suite covers, how
to run it locally, what each spec is testing, and how the CI matrix
slots it together with PHPUnit / PHPStan / Pint.

For docker-compose stack mechanics (how MySQL / Redis / Reverb are
brought up, `e2e:bootstrap` mechanics, Reverb port overrides,
common stack-bootstrap problems) see the sibling document
[docs/e2e-local.md](./e2e-local.md).

For diagnosing **failed** Playwright runs (where to find traces,
how to read screenshots, common false positives, single-spec retries)
see [docs/e2e-debug.md](./e2e-debug.md).

## What is covered

The Phase 0–6 rollout (PRs #91, #94–#98, plus this PR) brings the
suite from zero to **63 specs covering ≥ 99 % of the 77 user-visible
PRs in the upstream php8 branch**. The specs are organised by intent
into five top-level folders:

| Folder | Intent | Spec count |
| --- | --- | --- |
| `tests/e2e/smoke/` | Lightweight render-and-no-JS-error checks across the legacy + Livewire pages. Catches the kind of regression that breaks every page at once (a missing helper, a fatal during `stdhead()`). | 38 |
| `tests/e2e/behavior/` | Targeted UI behaviour tests that exercise specific upstream PRs (search-as-you-type debounce, ratio-tooltip `.ratio-tip` data, shoutbox JS wiring, Modern 2025 theme reverts). | 7 |
| `tests/e2e/admin/` | Filament admin panel + Horizon admin gate. Asserts admin auth gating, dashboard rendering, and resource-list endpoints don't 500 with the seeded admin. | 10 |
| `tests/e2e/announce/` | BitTorrent announce protocol smoke. Drives `/announce.php` over `APIRequestContext` (not a browser) and asserts the response is bencoded and reaches the peer-list code path. | 4 |
| `tests/e2e/critical/` | Negative / destructive paths: install bootstrap (#74), WebAuthn passkey enrollment (#405 / #427), and the failed-login rate limiter (`maxloginattempts`). | 6 |

Every spec re-uses the same three deterministic test users from
`database/seeders/E2eUsersSeeder.php`:

| Role | Username | Password | NexusPHP class | Tracker passkey |
| --- | --- | --- | --- | --- |
| `admin` | `e2eadmin` | `E2eAdmin2026` | `CLASS_STAFF_LEADER` (16) | `e2eadmine2eadmine2eadmine2eadm00` |
| `staff` | `e2estaff` | `E2eStaff2026` | `CLASS_MODERATOR` (13) | `e2estaffe2estaffe2estaffe2est000` |
| `user` | `e2euser` | `E2eUser2026` | `CLASS_USER` (1) | `e2eusere2eusere2eusere2euser0000` |

Auth in specs is performed via
`tests/e2e/helpers/api-login.ts :: loginAs(context, role)` — a single
HTTP `POST /takelogin.php` call that captures the `c_secure_pass`
session cookie and adds it to the test's `BrowserContext`. This skips
the JS-heavy login form and keeps each spec under 1 s.

## Running locally

```bash
# 0. Bring up the stack (idempotent). See docs/e2e-local.md for prereqs.
./scripts/e2e-stack-up.sh

# 1. Install Playwright browsers once (only Chromium is run by default).
npm run e2e:install

# 2. Run the full suite (the default base URL is http://localhost,
#    served by the openresty container).
npm run e2e

# 3. Or open the Playwright UI to step through specs interactively.
npm run e2e:ui

# 4. Tear the stack down when finished.
./scripts/e2e-stack-down.sh
```

The `npm run e2e` task uses `tests/e2e/playwright.config.ts`, which
pins:

- `baseURL = process.env.E2E_BASE_URL ?? 'http://localhost'`
- `fullyParallel: true`, with two workers in CI and unlimited locally.
- Two Playwright projects: a default `chromium` project that runs
  every spec in parallel, and a `destructive` project that runs after
  `chromium` finishes with **a single worker**. The `destructive`
  project is currently used only by `tests/e2e/critical/rate-limit.spec.ts`,
  which intentionally bans the requesting IP and would otherwise
  knock parallel `/login.php` smokes off course.
- `trace: 'retain-on-failure'`, `screenshot: 'only-on-failure'`,
  `video: 'retain-on-failure'`. Successful runs leave no artefacts.

To run a single spec:

```bash
E2E_BASE_URL=http://localhost \
    npx playwright test \
    --config=tests/e2e/playwright.config.ts \
    tests/e2e/critical/passkey-webauthn.spec.ts
```

To run a single project:

```bash
npm run e2e -- --project=chromium       # excludes destructive
npm run e2e -- --project=destructive    # only rate-limit
```

## CI matrix

The `e2e` GitHub Actions job in `.github/workflows/ci.yml` boots the
stack with `php -S 127.0.0.1:8000 -t public tests/e2e/server-router.php`
(no docker, just the built-in webserver + a tiny router shim that
mirrors openresty's `try_files` rule and seeds `REQUEST_SCHEME`).

The job:

1. Starts a `mysql:8.0` service on `127.0.0.1:3306`.
2. Starts a `redis:7-alpine` service on `127.0.0.1:6379`.
3. `composer install` + `npm ci` + `npm run build` (Vite manifest
   is required by the `@vite` Blade directive).
4. `php artisan migrate:fresh` + `php artisan e2e:bootstrap --force`.
5. Boots the PHP server in the background and probes `/`.
6. Runs `npm run e2e` against `127.0.0.1:8000`.

The job currently has `continue-on-error: true` so that early Phase
breakage does not block PRs. Phase 6 is the last phase that needs
this safety net — once Phase 6 lands and is stable for ≈ 1 week
the flag should be removed (tracking task).

## Adding a new spec

1. Copy a spec from the right intent folder (`smoke/`, `behavior/`,
   etc.) — pick the closest analogue.
2. If the spec needs auth, accept the `context` fixture and call
   `await loginAs(context, 'admin' | 'staff' | 'user')` at the top.
3. For protocol-level assertions (announce, install wizard, JSON
   APIs) prefer Playwright's `APIRequestContext` (`request.get(...)`,
   `request.post(...)`) over `page.goto(...)`. This is faster and
   bypasses asset-loading flakiness on single-threaded webservers.
4. For DOM assertions on legacy pages prefer `page.request.get(...)`
   + `response.text()` + raw substring checks over `page.locator(...)`
   when you don't need event-driven behaviour. The legacy NexusPHP
   front-end is HTML-only; a regex check is both faster and more
   robust than a Playwright locator.
5. If the spec is destructive (modifies global state in a way that
   would break parallel siblings), add it to the `destructive`
   project in `tests/e2e/playwright.config.ts` and clean up in
   `test.afterAll`.

The smoke helper `tests/e2e/helpers/smoke.ts :: smokeCheckPage()`
is the standard recipe for "this URL must render without fatals" —
prefer it over hand-rolling the same assertions.

## See also

- [`docs/e2e-local.md`](./e2e-local.md) — stack bootstrap & docker
  troubleshooting.
- [`docs/e2e-debug.md`](./e2e-debug.md) — diagnosing failed
  Playwright runs.
- [`tests/e2e/playwright.config.ts`](../tests/e2e/playwright.config.ts)
  — the source of truth for project layout, timeouts, and the
  base URL.
