<?php

/**
 * Router for PHP's built-in webserver (`php -S`) that mirrors the openresty
 * routing rules in `.docker/openresty/sites/app.conf.template` closely enough
 * for the Playwright e2e suite to run against the same code paths.
 *
 * Two reasons we cannot use `php -S` without a router:
 *
 * 1. `php -S` does NOT populate `$_SERVER['REQUEST_SCHEME']`,
 *    `$_SERVER['HTTPS']`, or `$_SERVER['HTTP_X_FORWARDED_PROTO']`.
 *    `Nexus::getRequestSchema()` reads them and crashes with `TypeError`
 *    when all three are unset (production sets them via fastcgi_param).
 *
 * 2. `php -S` only serves files that exist in the document root. SPA-style
 *    URLs (Livewire `/browse`, Filament `/nexusphp/...`) need to fall
 *    through to `public/nexus.php`, the Laravel front controller.
 *    The openresty config implements this via:
 *
 *        location / {
 *            try_files $uri $uri/ /nexus.php?$query_string;
 *        }
 *
 *    This router replicates that fallback.
 */
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'off';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http';

$publicDir = realpath(__DIR__.'/../../public');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Legacy NexusPHP scripts use relative `require '../include/bittorrent.php'`
// from inside `public/`. That only resolves correctly when the current
// working directory is the document root. `php -S` keeps cwd at the
// directory it was launched from (the repo root in CI), so any `require`
// or `include` we issue from this router needs to chdir first.
chdir($publicDir);

// "/" -> public/index.php (NexusPHP legacy home; matches `index index.php`
// in the openresty server block).
if ($uri === '/' || $uri === '' || $uri === null) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $publicDir.'/index.php';
    require $publicDir.'/index.php';

    return true;
}

// Existing file in public/. We do NOT `return false` to let the built-in
// server serve directly: that path bypasses the `$_SERVER` schema/HTTPS
// shim above and re-spawns the script with a fresh `$_SERVER` that still
// has no `REQUEST_SCHEME`. NexusPHP's `Nexus::getRequestSchema()` then
// crashes with `TypeError: getFirst() argument must be of type string,
// null given`. Instead we serve PHP files via `require` (so the shim is
// preserved) and only fall back to the built-in static-file handler for
// non-PHP assets.
$candidate = $publicDir.$uri;
if (is_file($candidate)) {
    if (str_ends_with(strtolower($candidate), '.php')) {
        $_SERVER['SCRIPT_NAME'] = $uri;
        $_SERVER['SCRIPT_FILENAME'] = $candidate;
        require $candidate;

        return true;
    }

    return false;
}

// Anything else falls through to the Laravel front controller. This handles
// Livewire (`/browse`, `/livewire/update`), Filament (`/nexusphp/...`) and
// any other route registered in `routes/web.php`.
$_SERVER['SCRIPT_NAME'] = '/nexus.php';
$_SERVER['SCRIPT_FILENAME'] = $publicDir.'/nexus.php';
require $publicDir.'/nexus.php';

return true;
