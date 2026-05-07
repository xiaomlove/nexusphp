# Local end-to-end (Playwright) stack

This guide covers how to bring the full NexusPHP stack up locally so
that Playwright (or a manual browser session) can hit a real running
copy of the app — backed by the same MySQL, Redis, openresty/nginx,
and Reverb services that production uses.

## TL;DR

```bash
./scripts/e2e-stack-up.sh
```

That command is idempotent — re-running it brings the stack into a
known-good state without recreating volumes. Once it returns
`[OK] stack is up:`, you can:

- Visit `http://localhost/` (legacy front-end).
- Visit `http://localhost/browse` (Livewire `TorrentBrowse`).
- Visit `http://localhost/nexusphp` (Filament admin SPA).
- Connect a websocket client to `ws://localhost:8080`
  (Reverb) for real-time tests.

To shut it down:

```bash
./scripts/e2e-stack-down.sh
```

## Requirements

- Docker (≥ 24)
- Docker Compose v2
- `curl`, `bash`, `nc`

## Test users

`scripts/e2e-stack-up.sh` invokes `php artisan e2e:bootstrap`, which
creates three deterministic users (idempotently) via the same
`UserRepository::store()` code path the install wizard uses:

| Username   | Password         | Class                       | ID    |
|------------|------------------|-----------------------------|-------|
| `e2eadmin` | `E2eAdmin2026`   | `CLASS_STAFF_LEADER` (16)   | `1`   |
| `e2estaff` | `E2eStaff2026`   | `CLASS_MODERATOR` (13)      | auto  |
| `e2euser`  | `E2eUser2026`    | `CLASS_USER` (1)            | auto  |

If you change a password here, also update
`tests/e2e/fixtures/auth.ts` and `database/seeders/E2eUsersSeeder.php`
in the same commit.

## How `e2e:bootstrap` differs from production install

The legacy install wizard (`public/install/install.php`) runs
multistep validation, asks for an admin user via a form, and writes a
`.env` file. For tests we skip the wizard entirely; instead the
`e2e:bootstrap` artisan command:

1. Runs `SettingsTableSeeder` if the `settings` table is empty.
   `DatabaseSeeder` does not include settings, so without this step
   `Setting::get('main.defstylesheet')` returns null and any user
   creation throws an SQL `NOT NULL` integrity error.
2. Sets two settings to make plain HTTP login work:
   - `security.iv = no` — disables the image CAPTCHA. With it on,
     every `POST /takelogin.php` returns *Invalid Image Code*
     because no session has issued an image token.
   - `security.use_challenge_response_authentication = no` —
     disables the JS challenge-response flow on `login.php`. With
     it on, a plain form post to `takelogin.php` returns
     *Require response parameter*.
3. Runs `E2eUsersSeeder` to create the three test users (skips
   any that already exist).
4. Creates `dont_delete_install.lock` at the repository root. The
   PHP container sets `RUNNING_IN_DOCKER=1`; without the lock file
   `include/core.php` redirects every request to
   `install/install.php`.
5. Flushes the cached `nexus_settings_in_*` Redis keys.

## Common operations

### Reset everything (drop volumes, re-migrate, re-seed)

```bash
E2E_RESET=1 ./scripts/e2e-stack-up.sh
```

### Re-run only the bootstrap (after manually mucking with settings)

```bash
docker compose exec php php artisan e2e:bootstrap
docker compose restart php       # PHP-FPM static $settings cache
```

### Tail logs

```bash
docker compose logs -f php       # FPM access log + INFO_NEXUS lines
docker compose logs -f openresty # nginx access/error log
docker compose logs -f reverb    # Reverb websocket
docker compose logs -f queue     # Horizon
docker compose logs -f scheduler # Laravel scheduler
```

### Run artisan commands

```bash
docker compose exec php php artisan migrate:status
docker compose exec php php artisan tinker
docker compose exec php php artisan horizon:list
```

### Talk to the database

```bash
docker compose exec mysql \
  mysql -u nexusphp -pnexusphp nexusphp -e 'SELECT COUNT(*) FROM users;'

# phpMyAdmin (port published only when openresty proxies it):
docker compose exec phpmyadmin sh -c 'echo $PMA_HOST'
```

## Troubleshooting

### `MYSQL_USER="root", MYSQL_USER and MYSQL_PASSWORD ... cannot be used for the root user`

The `mysql:9` entrypoint refuses `MYSQL_USER=root`. The
`scripts/e2e-stack-up.sh` script forces `DB_USERNAME=nexusphp` to
work around this. If you set `DB_USERNAME=root` manually, the
container will fail to start.

### `Invalid Image Code!` on login

`security.iv` got set back to `yes`. Re-run
`docker compose exec php php artisan e2e:bootstrap`, then
`docker compose restart php`.

### `Require response parameter.` on login

`security.use_challenge_response_authentication` got set back to
`yes`. Same fix.

### Every page redirects to `install/install.php`

`dont_delete_install.lock` is missing. Same fix.

### Reverb on port 8080 conflicts with another service

Set `NP_REVERB_PORT` to something else before running the up script:

```bash
NP_REVERB_PORT=8090 ./scripts/e2e-stack-up.sh
```

Also update `REVERB_PORT` in `.env` and the `VITE_REVERB_PORT` value.

### `nexusphp_php` image rebuild forever

```bash
docker compose down -v --remove-orphans
docker rmi nexusphp_php nexus-test1-openresty
./scripts/e2e-stack-up.sh
```
