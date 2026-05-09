# Contributing to NexusPHP

Thanks for taking the time to send a patch! Two rules to read first.

## 1. Phase 0 — legacy freeze (hard rule)

The repository is in the middle of a Strangler-Fig migration from a
procedural-PHP layer to Laravel + Filament + Livewire. **No new files
may be added** to the legacy layer:

- `public/*.php` — top-level legacy entry points
- `include/**/*.php` — `bittorrent.php`, `functions.php`, `globalfunctions.php`, …
- `classes/**/*.php` — the old class hierarchy

New routes, pages, helpers, jobs, services, and tests go into the
Laravel layer:

- `app/Http/Controllers/`, `app/Models/`, `app/Services/`, `app/Livewire/`
- `routes/web.php`, `routes/api.php`
- `resources/views/` (Blade), Filament resources under `app/Filament/`
- `database/migrations/`, `database/seeders/`, `database/factories/`
- `tests/Unit/`, `tests/Feature/`, `tests/e2e/`

Modifications and deletions in the legacy paths are **encouraged** —
the legacy LOC count should shrink, not grow. The CI job
`Legacy freeze / No new legacy code` enforces this on every PR by
checking `git diff --diff-filter=A` against the merge base.

### Bypassing the freeze (rare)

Add the `legacy-allowed` label to the PR and justify the addition in
a comment. Acceptable reasons are short — security patches that have
to live in the legacy bootstrap, plumbing for a planned-and-imminent
migration of the same file. "We can refactor later" is not an
acceptable reason: if it's worth writing, it's worth writing in
the new layer.

### Migration roadmap

See [`docs/legacy-strategy.md`](docs/legacy-strategy.md) for the
5-phase plan that this freeze unlocks.

## 2. CI must be green

Every PR runs:

| Check | Command (local equivalent) | Notes |
|---|---|---|
| Code style | `vendor/bin/pint --test` | Laravel Pint |
| Static analysis | `vendor/bin/phpstan analyse` | Larastan, level set in `phpstan.neon` |
| Unit tests | `vendor/bin/phpunit --testsuite=Unit` | runs across PHP 8.2 / 8.3 / 8.4 |
| Feature tests | `vendor/bin/phpunit --testsuite=Feature` | needs MySQL + Redis services |
| E2E smoke | `npm run e2e -- --project=chromium` | Playwright against the docker stack |
| Legacy freeze | `bash scripts/legacy-loc.sh` (locally) | the rule above |

Run them locally before pushing — CI feedback loops are slow.

## 3. Local development

The fastest path is the docker-compose stack:

```bash
cp .env.example .env
sed -i 's|^DB_HOST=.*|DB_HOST=mysql|; s|^REDIS_HOST=.*|REDIS_HOST=redis|; s|^LOG_FILE=.*|LOG_FILE=php://stdout|' .env

cat > .env.compose <<'EOF'
NP_DOMAIN=localhost
NP_PORT=80
NP_REVERB_PORT=8080
NP_BACKUP_EXPORT_PATH=/tmp/nexusphp_backup
DB_USERNAME=nexusphp
DB_PASSWORD=nexusphp
DB_DATABASE=nexusphp
EOF

docker compose --env-file .env.compose up -d --build
docker exec nexusphp-php php artisan migrate --force
docker exec nexusphp-php php artisan e2e:bootstrap --force
```

App: <http://localhost> · Filament admin: <http://localhost/nexusphp> · login: `e2eadmin / E2eAdmin2026`

## 4. Branch & PR conventions

- Branch off `php8`. Don't push directly to `php8` / `main` / `master`.
- One PR per change; keep diffs scoped. Refactors and feature changes
  in the same PR get rejected.
- PR title: `<area>: <imperative verb> <subject>` — e.g.
  `tracker: drop ORDER BY RAND() in announce peer sample`.
- Reference the legacy strategy phase in the description if the PR
  migrates a legacy file (e.g. `Phase 2: migrates contactstaff.php`).
