# AGENTS.md

Execution guide for coding agents working in this repository.

## Project Profile

- Stack: `Laravel 12 + Filament 5 + legacy NexusPHP`.
- Product: full private PT tracker platform (upload, approval, H&R, attendance, medals, forum, plugins, etc.).
- Architecture is hybrid: modern Laravel (`app/`) + legacy modules (`nexus/`, `include/`, `classes/`).

## Start Here: Bring Up The Full Site (Docker First)

Agents should prefer Docker to boot the full system for validation.

### 1) Start all services

```bash
docker compose up -d --build
```

Services include: `php`, `openresty`, `mysql`, `redis`, `queue`, `scheduler`, `cleanup`, `phpmyadmin`.

### 2) Check service health

```bash
docker compose ps
docker compose logs -f php openresty
```

### 3) First-time install flow (if not installed yet)

- If `.env` or `vendor/` is missing, container startup prepares the installer automatically (handled by `.docker/php/entrypoint.sh`).
- Open site root (default `http://localhost`) and complete the web installer.
- If installer shows `Locked! Delete .lock file first`, remove `dont_delete_install.lock` only when reinstall is intended.

### 4) Useful in-container commands

```bash
docker compose exec php php artisan --version
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --force
docker compose exec php php artisan horizon:status
docker compose exec php php artisan route:list
docker compose exec php php artisan migrate:status
```

## Directory Map (Key Areas)

Use this map to quickly locate business logic and integration points.

```text
nexusphp/
├── app/                           # Laravel app layer
│   ├── Filament/                  # Filament admin resources/pages/widgets
│   ├── Http/                      # Controllers, middleware, request entry points
│   ├── Models/                    # Eloquent models
│   ├── Repositories/              # Core business logic (high priority for feature changes)
│   ├── Console/Commands/          # Operational/business commands
│   └── Exceptions/                # Unified exception handling and API error style
├── nexus/                         # Legacy NexusPHP core
│   ├── Install/                   # Installer/updater workflow
│   ├── Database/                  # Legacy DB wrapper/helpers
│   └── Plugin/                    # Plugin infrastructure
├── include/                       # Legacy global functions/bootstrap glue
├── classes/                       # Legacy class library
├── routes/                        # Route definitions (web/api/admin/console/...)
├── resources/                     # Laravel-side views/assets/translations
│   ├── views/                     # Blade views, including Filament custom views
│   ├── js/                        # Frontend entry files
│   └── css/                       # Tailwind entry files
├── lang/                          # Legacy language packs
├── database/
│   ├── migrations/                # Main DB migrations
│   ├── clickhouse-migrations/     # ClickHouse migrations
│   └── seeders/                   # Seeders
├── public/                        # Web root (`nexus.php`, static assets, install copies)
├── config/                        # Laravel + project configs
├── .docker/                       # Dockerfiles and entrypoint scripts (preferred local runtime)
├── docker-compose.yml             # Full local service orchestration
├── composer.json                  # PHP dependencies and runtime constraints
└── package.json                   # Frontend build scripts (Laravel Mix)
```

## Agent Navigation Priority

1. First determine if change belongs to Laravel layer (`app/`) or legacy layer (`nexus/ + include/ + classes/`).
2. For business behavior, inspect `app/Repositories/` first, then trace to Controller/Model.
3. For install/bootstrap/runtime issues, inspect `nexus/Install/` and `.docker/*/entrypoint.sh` first.
4. For admin panel behavior, inspect `app/Filament/` and `resources/views/filament/`.

## Project-Direct Validation Commands

### Laravel-side quick checks

```bash
php artisan --version
php artisan route:list
php artisan migrate:status
```

### Docker-side quick checks (recommended)

```bash
docker compose exec php php artisan --version
docker compose exec php php artisan route:list
docker compose exec php php artisan migrate:status
docker compose exec php php -m
```

## Business Change Guidance

- Prefer implementing new features in Laravel layer (`app/Repositories` + `app/Http/Controllers` + `app/Models`).
- When legacy compatibility is required, evaluate whether `nexus/` or `include/` must be updated together.
- For critical site behavior (permissions, upload flow, H&R, reward logic, approval), inspect related Repository and Setting path first.
- Keep API/exception response shape consistent with existing project conventions (for example `fail(...)` usage patterns).

## General Coding Conventions (Keep Together, Secondary)

- Follow `.editorconfig` (UTF-8, LF, 4 spaces, final newline).
- PHP naming: class `PascalCase`, method/variable `camelCase`, migration files keep timestamp naming.
- Keep `use` imports clean; avoid unused imports.
- Do not silently swallow exceptions; throw clear exceptions with context when needed.
- Keep changes minimal and scoped; avoid unrelated refactors.

## Agent Self-Check Before Hand-off

- `docker compose ps` shows core services healthy.
- Site root or admin login page is reachable (at least HTTP layer works).
- Changed behavior has been minimally validated (at least one command/page/API path).
- No unrelated file churn is included.
