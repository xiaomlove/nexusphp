---
name: phase-2-migration
description: |
  How to migrate one ≤100-LOC legacy public/*.php page to a Laravel
  controller (or Artisan command, for "scheduled job hiding behind
  HTTP" pages), and how to sweep the satellite references that
  always come with the deletion (langfiles, sysop-panel seeder,
  Update.php removeMenu, dedicated docker containers).
  Use when: the task mentions "Phase 2", "migrate page", "delete
  legacy", or asks to drop a specific public/*.php file.
---

# Phase 2 migration — operational skill

This skill is the high-density companion to `docs/migration-recipe.md`.
The doc covers the *theory* and *worked example*; this file covers
the *commands you actually run* in this repo, in the order they
work, with the gotchas the doc doesn't repeat.

## When to use this skill

- Task explicitly mentions Phase 2, or names a specific
  `public/*.php` file ≤100 LOC to migrate.
- Task is a follow-up cleanup after such a migration (drop dead
  langfiles, comment out menu row, add `removeMenu` to Update.php).
- Task asks to find more legacy pages to migrate.

**Do NOT use** for `announce.php` / `scrape.php` (Phase 4 — these
require non-Laravel handling), or for ≥100-LOC pages with
`$Cache->new_page()` / `add_part()` (Phase 3 — Livewire territory).

## Required reading

1. `docs/legacy-strategy.md` — *why* and in what order.
2. `docs/migration-recipe.md` — *how*, step-by-step. Especially
   **Step 5.5 (post-deletion sweep)** and the Phase 2 sub-pattern
   "scheduled job hiding behind HTTP" sections.
3. `CONTRIBUTING.md` — branch policy, "one PR / one change",
   CI requirements.

## Local commands (verified in this snapshot's env)

The env-blueprint puts PHP 8.3 + composer + git-hooks on the box.
After `composer install`, all of these work without further setup:

```bash
# Code style — applies to changed PHP files only in CI, but you
# can scope it locally to your edits:
vendor/bin/pint --test path/to/changed.php
vendor/bin/pint path/to/changed.php           # auto-fix

# Static analysis — scans app/ paths per phpstan.neon (CI does the
# same; you don't normally pass file paths because that bypasses
# the baseline scope and surfaces noise):
vendor/bin/phpstan analyse --memory-limit=2G --no-progress

# Baseline guard — fails if your branch added more entries than
# origin/php8 has. Run after every PHPStan-touching change:
bash scripts/phpstan-baseline-guard.sh

# Legacy bucket count — watermark for the strangler-fig migration:
bash scripts/legacy-loc.sh

# Tests — Feature/Console subsuites cover the legacy migrations:
vendor/bin/phpunit --testsuite=Feature --filter <YourTest>
vendor/bin/phpunit tests/Feature/Console/CleanupFullCommandTest.php
```

## The two migration shapes

### Shape A — regular HTTP page → Laravel controller

Use when the legacy page renders HTML/JSON for a user. The full
walk-through is the worked example in `migration-recipe.md`.
Skeleton:

```
app/Http/Controllers/Legacy/<Name>Controller.php   # invokable
routes/web.php                                     # Route::any → controller
tests/Feature/Legacy/<Name>ControllerTest.php
public/<page>.php                                  # git rm
```

CSRF / FormRequest / Locale-middleware pitfalls are documented in
`migration-recipe.md` § "Common test pitfalls (Phase 2 lessons)" —
read those *before* writing the Feature test.

### Shape B — "scheduled job hiding behind HTTP" → Artisan command

Use when the legacy file is gated on sysop class and is essentially
an ops button (`curl /docleanup.php?forceall=1`). Precedent:
- `public/cron.php` → `cron:autoclean` (Phase 2.1.b).
- `public/docleanup.php` → `cleanup:full --force-all` (Phase 2.1.c,
  PR #193).

Skeleton:

```
app/Console/Commands/<Name>.php                    # extends Command
tests/Feature/Console/<Name>CommandTest.php        # pins signature
public/<page>.php                                  # git rm
```

Key differences from Shape A:

- **No route, no CSRF, no FormRequest, no view.**
- Do **not** auto-register in `app/Console/Kernel::schedule()` unless
  ops wants it on a schedule. Default: manual `docker exec ...`.
- Pin the signature and flag declaration in the test; full
  behavioural coverage of the legacy function is deferred to
  Phase 5.

## Post-deletion sweep (the rule that bit me)

Deleting `public/<page>.php` only kills the entry point. Every
legacy page in this codebase tends to ship 3-5 satellite
references. Sweep them in the same PR (or in clearly-named
follow-up PRs, one satellite at a time — the docleanup sequence
#193 → #194 → #195 → #196 is the worked precedent).

Run these greps **before** declaring the migration done:

```bash
# 1. Language dictionaries (~19 locales × ~5-30 LOC each)
grep -rln "get_langfile_path(['\"]\?<page>" . | grep -v vendor/
grep -rln "lang_<page>" --include="*.php" .            \
  | grep -v vendor/ | grep -v "^./lang/.*/lang_<page>.php$"

# 2. Sysop-panel seeder
grep -n "'<page>.php'" database/seeders/*.php

# 3. Update.php removeMenu (search for similar patterns)
grep -n "removeMenu" nexus/Install/Update.php

# 4. CLI wrapper + dedicated docker container
ls include/<page>_cli.php 2>/dev/null
grep -n "<page>" docker-compose.yml .docker/php/entrypoint.sh

# 5. Live href / Location: / nexus_redirect references in legacy templates
grep -rn "<page>\.php" --include="*.php" .             \
  | grep -v vendor/ | grep -v "^./public/<page>.php"

# 6. Regex / config-array references — known location is
#    include/globalfunctions.php::filter_src() $dangerScriptsPattern.
#    Artisan-shape migrations leave these dead; controller-shape
#    keep them alive because the Route::any('/<page>.php', ...)
#    line preserves the URL.
grep -rln "<page>" --include="*.php" .                 \
  | grep -vE '(/lang/|/docs/|legacy-strategy.md|migration-recipe.md|SKILL.md)'
```

If hits in (2) appear, you'll need the **two-commit pattern**
because the 2021-era `SysoppanelTableSeeder.php` has pre-existing
Pint violations that block any subsequent edit:

```
git checkout -b devin/<ts>-<short-name>
# commit (a): pure reformat, no functional change
vendor/bin/pint database/seeders/<...>TableSeeder.php
git add database/seeders/<...>TableSeeder.php
git commit -m "style: pint reformat <...>TableSeeder.php (pre-existing violations)"

# commit (b): functional change on top of the clean baseline
# edit the file to comment out the dead row
git add database/seeders/<...>TableSeeder.php nexus/Install/Update.php
git commit -m "legacy: drop <page> menu (post-#<...> followup)"
```

If hits in (3) appear, add to `nexus/Install/Update.php::runExtraQueries()`:

```php
        /**
         * @since <next-version>
         * `public/<page>.php` was removed in Phase 2 (PR #<n>). ...
         */
        $this->removeMenu(['<page>.php']);
```

Pattern reference: existing `@since 1.7.12` (deletedisabled /
amountupload / amountattendancecard / amountbonus), `@since
1.7.19` (freeleech), and `catmanage.php` blocks already do this.

## Branch / PR conventions

- Branch off `origin/php8` (never `main`/`master`).
- Branch name: `devin/$(date +%s)-<short-name>`.
- One PR / one change (CONTRIBUTING.md §1). If sweep finds 3+
  satellites, that's OK to split into a sequence of follow-up
  PRs — name them so the relationship is obvious (`legacy: drop
  X (post-#<migration-PR> cleanup)`).
- PR title format: `<area>: <imperative verb> <subject>`
  (`legacy: migrate ... (Phase 2)`, `legacy: drop ...`, `style:
  pint reformat ...`).
- Wait for CI to be **8/8 green** before declaring done. See
  CONTRIBUTING.md §2.

## Local checks → CI mapping

| CI job              | Local equivalent                                                  |
|---------------------|-------------------------------------------------------------------|
| Code style (Pint)   | `vendor/bin/pint --test <changed files>`                          |
| PHPStan / Larastan  | `vendor/bin/phpstan analyse --memory-limit=2G --no-progress`      |
| PHPStan baseline    | `bash scripts/phpstan-baseline-guard.sh`                          |
| PHPUnit (PHP 8.3)   | `vendor/bin/phpunit --testsuite=Unit`                             |
| PHPUnit Feature     | `vendor/bin/phpunit --testsuite=Feature --filter <Name>`          |
| E2E (Playwright)    | `docker compose --env-file .env.compose up -d` + `bash scripts/e2e/run.sh` (see `docs/e2e-local.md`) |
| No new legacy code  | `bash scripts/legacy-loc.sh` + `bash scripts/legacy-freeze.sh` (CI runs the freeze guard) |
| CodeQL / Analyze    | (CI-only)                                                         |

Pint and PHPStan are also wired into the **pre-commit hook** that
`scripts/install-hooks.sh` installs (env-blueprint runs this in
maintenance). Hook runs both against the staged file set only.

## Don'ts

- Don't add `--no-verify` to a commit unless explicitly asked.
- Don't add files to `phpstan-baseline.neon` to silence new errors.
  Fix the error or restructure the call site.
- Don't reformat unrelated files in a functional PR. If a touched
  file has pre-existing Pint violations, isolate the reformat in
  its own commit (or its own PR if the reformat is huge).
- Don't merge a regular `legacy:` migration PR without running
  the post-deletion sweep, or at minimum scheduling the follow-up
  PRs in the description.
- Don't add new files under `public/`, `include/`, `classes/` —
  CI's `Legacy freeze` job blocks the PR (override only with the
  `legacy-allowed` label, and only for cherry-pick PRs from
  upstream NexusPHP).

## Reference: PR sequence for the docleanup migration

A worked end-to-end example, in case you need to cite the pattern
in a description:

- **#193** — `legacy: migrate docleanup.php to cleanup:full Artisan
  command (Phase 2)`. The migration itself: 30 LOC out of `public/`,
  ~80 LOC into `app/Console/Commands/CleanupFull.php` +
  `tests/Feature/Console/CleanupFullCommandTest.php`.
- **#194** — `legacy: drop include/cleanup_cli.php + redundant
  docker cleanup container`. The sweep found a `cleanup_cli.php`
  wrapper + a `nexusphp-cleanup` docker container doing the same
  work the new `cron:autoclean` schedule already covered.
- **#195** — `legacy: drop 19 dead lang/<locale>/lang_docleanup.php
  files (post-#193)`. Sweep step 1 (language dictionaries).
- **#196** — `legacy: drop sysoppanel "Do cleanup" menu + Update.php
  removeMenu (final docleanup cleanup)`. Sweep steps 2 + 3 in a
  two-commit PR (pint reformat, then functional change).

Total: 1 migration PR + 3 sweep PRs. The sweep found more dead
code than the migration itself removed.
