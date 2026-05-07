<?php

/**
 * Minimal router for PHP's built-in webserver (`php -S`) so legacy NexusPHP
 * pages can be served without an upstream proxy.
 *
 * `php -S` does NOT populate `$_SERVER['REQUEST_SCHEME']`, `$_SERVER['HTTPS']`,
 * or `$_SERVER['HTTP_X_FORWARDED_PROTO']`. NexusPHP's `Nexus::getRequestSchema()`
 * derefences whichever of those is set first, then passes it through a typed
 * `string` argument; an unset value crashes the request with `TypeError`.
 *
 * In production NexusPHP runs behind nginx/openresty (see docker-compose.yml),
 * which sets these via fastcgi_param. Browser-driven Playwright tests against
 * `php -S` need this router so the page can boot and call `header('Location: ...')`
 * without crashing.
 *
 * The router seeds the missing $_SERVER keys for every request and then returns
 * `false`, which tells the built-in server to serve the requested file from the
 * document root as if no router were configured.
 */
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'off';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http';

return false;
