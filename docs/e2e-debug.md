# Debugging failed E2E runs

When `npm run e2e` (or the `e2e` CI job) goes red, this is the
checklist that gets you from a stack-trace to a fix in the
shortest path.

For high-level "what's covered & how do I add a spec" content, see
[`docs/e2e.md`](./e2e.md). For "the docker stack won't even boot",
see [`docs/e2e-local.md`](./e2e-local.md).

## The 30-second triage

1. **Re-run the failed spec in isolation:**

   ```bash
   E2E_BASE_URL=http://localhost \
       npx playwright test \
       --config=tests/e2e/playwright.config.ts \
       tests/e2e/<folder>/<file>.spec.ts \
       --workers=1
   ```

   If it now passes, the failure was a *parallel-execution
   interaction* — see "Parallel-execution flakiness" below.

2. **Open the trace:**

   ```bash
   npx playwright show-trace test-results/<failed-test-name>/trace.zip
   ```

   The trace timeline shows every navigation, every network request,
   and the DOM at each step. 90 % of failures are obvious from the
   trace alone (wrong URL, missing cookie, page actually rendered an
   error template).

3. **Read `error-context.md`:**

   For every failure Playwright emits a markdown file at
   `test-results/<failed-test-name>/error-context.md` that contains
   a YAML snapshot of the page's accessibility tree at the moment of
   failure. This is much faster to read than the screenshot — if
   the snapshot does NOT contain the element you're looking for,
   the page didn't render it. Either the test URL is wrong or the
   server returned the wrong template.

4. **Re-bootstrap the stack:**

   ```bash
   docker compose exec -T php php artisan e2e:bootstrap --force
   ```

   That command is idempotent. It re-seeds the three e2e users, sets
   the E2E-friendly security flags (CAPTCHA off, challenge-response
   auth off), touches `dont_delete_install.lock`, and crucially
   clears the `loginattempts` table. If any prior run left an IP
   banned, this resets it.

## Reading Playwright artefacts

`tests/e2e/playwright.config.ts` ships with three retain policies:

```
trace:      'retain-on-failure'
screenshot: 'only-on-failure'
video:      'retain-on-failure'
```

Successful runs leave nothing in `test-results/`. A failed run leaves:

- `test-results/<test>-chromium/trace.zip` — interactive trace,
  open with `npx playwright show-trace <path>`.
- `test-results/<test>-chromium/error-context.md` — accessibility
  tree snapshot at the failure point.
- `test-results/<test>-chromium/video.webm` — full recording of the
  worker that ran the spec.
- `test-results/<test>-chromium/test-failed-1.png` — final
  screenshot.
- `playwright-report/index.html` — HTML aggregator across all
  failures. Open with `npx playwright show-report`.

## Common failure modes

### `Login Locked! (... maximum number of failed 10 attempts ...)`

The `loginattempts` table is populated for the runner's IP. Either
a previous `rate-limit.spec.ts` run did not clean up, or some other
spec is hammering bad credentials.

```bash
docker compose exec -T php php artisan e2e:bootstrap --force
```

Re-runs the bootstrap which truncates `loginattempts`.

### `expect(locator).toBeVisible() failed` on `/login.php` or `/usercp.php`

Most likely the page returned a partial body (only the head and
top-of-page navigation, with no form). Two known causes:

- **PHP-FPM pool saturation.** The Phase 6 image bumps
  `pm.max_children` to 20 in `.docker/php/entrypoint.sh`. If your
  image was built before that change, rebuild:
  `docker compose build php && docker compose up -d php`.
- **IP banned mid-test.** See "Login Locked!" above.

If neither applies, capture the response body manually:

```bash
curl -i -b /tmp/jar.txt http://localhost/usercp.php?action=security \
    | tee /tmp/dump.html
```

If `curl` shows a full body but Playwright sees only the head, the
issue is in the browser context's response — usually a leaked
cookie from another spec. Check the `Application` tab in the trace.

### `apiRequestContext.get: Invalid URL`

`process.env.E2E_BASE_URL` was not set. Run with:

```bash
E2E_BASE_URL=http://localhost npm run e2e
```

The `playwright.config.ts` falls back to `http://localhost`, but
when you invoke `npx playwright test` directly (without a config)
you bypass that. Always pass `--config=tests/e2e/playwright.config.ts`
for ad-hoc runs.

### `Project(s) "chromium" not found. Available projects: ""`

Same root cause: you ran `npx playwright test` without
`--config=tests/e2e/playwright.config.ts`. Use `npm run e2e --
--project=chromium` instead, or pass the config explicitly.

### A new spec passes solo but fails in the full run

Parallel-execution flakiness. The default `fullyParallel: true`
means up to N specs run concurrently, sharing the same MySQL +
Redis + PHP-FPM pool. Most flakiness collapses to one of:

- The other spec is mutating global server state (login attempts,
  redis cache keys, settings rows). Either move your spec into the
  `destructive` project (which runs last with `workers: 1`) or
  isolate the mutating spec.
- Your spec relies on a row that the other spec just deleted.
  Use seeded fixtures (`E2eUsersSeeder`) instead of relying on
  ambient seeded data.
- Two specs with the same `loginAs(...)` role both clear and
  re-set the same Redis cache key. Add a `await
  page.context().clearCookies()` at the top of the spec to start
  from a known-clean state.

### `Welcome, e2eadmin … <empty body>` and the test times out

You logged in but the response stopped streaming. Almost always a
PHP-FPM pool saturation symptom — see above. If the issue persists
after rebuilding the php image, dig into
`docker compose logs --tail=200 php` for `[ERROR]` markers.

### `apiCtx.post('/takelogin.php')` returns 200 instead of 30x

The E2E-friendly security flags were not set. Re-run
`php artisan e2e:bootstrap --force`. If that does not fix it, check:

```bash
docker compose exec -T php php artisan tinker
> get_setting('security')['iv']
> get_setting('security')['use_challenge_response_authentication']
```

Both must be `'no'`. If they read `'yes'`, the cache is stale —
redis ate the bootstrap update. Force a flush:

```bash
docker compose exec -T redis redis-cli FLUSHALL
docker compose restart php
```

### Vite manifest not found / `/browse` 500s

`npm run build` did not run. The Filament panel and the Livewire
`/browse` route both reference `@vite('resources/...')` in Blade
which requires `public/build/manifest.json`. Run:

```bash
npm run build
```

…before `npm run e2e`. The CI workflow does this automatically.

### Reverb connection refused on `:8080`

The Reverb service failed to boot. Check:

```bash
docker compose logs --tail=100 reverb
```

If the log shows `php artisan reverb:start` looping, the most
common cause is `.env` `REVERB_APP_ID`/`REVERB_APP_KEY`/
`REVERB_APP_SECRET` not being set. Re-run
`./scripts/e2e-stack-up.sh` which seeds those.

## Bisecting a single failing spec

When the trace doesn't make the cause obvious, run with:

```bash
E2E_BASE_URL=http://localhost \
    DEBUG=pw:api \
    npx playwright test \
    --config=tests/e2e/playwright.config.ts \
    --headed \
    --workers=1 \
    --max-failures=1 \
    tests/e2e/<folder>/<file>.spec.ts
```

`--headed` opens a browser window so you can see the spec fail in
real time. `DEBUG=pw:api` logs every Playwright API call. Combined
they usually pinpoint the misbehaving line in under a minute.

## Resetting from scratch

If the stack is in an unrecoverable state (Redis gunked up, MySQL
locked, PHP-FPM crashed):

```bash
./scripts/e2e-stack-down.sh
docker compose down -v --remove-orphans
docker rmi nexusphp_php nexus-test1-openresty 2>/dev/null
./scripts/e2e-stack-up.sh
```

This burns the volumes (mysql data, redis state) and re-builds the
php image. `e2e-stack-up.sh` will re-run migrations and re-bootstrap.

## Filing a bug

When you cannot reproduce the failure or the fix is non-obvious,
attach to the issue:

1. The full Playwright HTML report
   (`zip -r report.zip playwright-report test-results`).
2. The `docker compose logs` from `php`, `openresty`, and `mysql`
   (last 200 lines each).
3. The exact command you ran (including `E2E_BASE_URL`).
4. The output of `git rev-parse HEAD` so we know what branch state
   produced the failure.
