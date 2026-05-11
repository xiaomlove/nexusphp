<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wrapped legacy pages
    |--------------------------------------------------------------------------
    |
    | The set of legacy procedural-PHP entry points that are safe to wrap in
    | a Laravel route via `App\Http\Controllers\Legacy\LegacyPageController`.
    | This is the in-between step in the strangler-fig migration: the page
    | source still lives in `public/`, but its request runs through the full
    | Laravel pipeline (CSRF, rate limit, structured logs, Sentry).
    |
    | A page may be added here only after a manual review confirms that:
    |
    |   - it does not call `exit` / `die` on its happy path (those would
    |     terminate the Laravel worker mid-response);
    |   - it does not stream binary data or `Set-Cookie` after `stdhead()`
    |     (those need a dedicated controller, not the wrap);
    |   - an E2E smoke spec exists for the URL (`tests/e2e/smoke/`).
    |
    | The map keys are page names (no `.php`); the values are absolute
    | filesystem paths to the legacy entry points. Routes are registered
    | for each entry by `routes/web.php` at boot time.
    |
    | This list intentionally starts EMPTY. The first wrap is its own PR
    | (Phase 1.4) so the process-isolation review is explicit.
    |
    */

    'wrapped_pages' => [
        // 'logout' => base_path('public/logout.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy bootstrap path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the legacy bootstrap script (`include/bittorrent.php`).
    | `LegacyChrome` requires this on first call to bring `stdhead()` /
    | `stdfoot()` (and the pile of globals they read) into scope.
    |
    | Override in tests to point at a fixture that defines lightweight
    | versions of these functions instead of pulling the real include
    | chain. See `tests/Feature/Legacy/LegacyChromeTest.php`.
    |
    */

    'bootstrap_file' => base_path('include/bittorrent.php'),

    /*
    |--------------------------------------------------------------------------
    | Legacy chrome function names
    |--------------------------------------------------------------------------
    |
    | Names of the global procedural functions `LegacyChrome` calls into
    | to render the site head and foot. Defaults to the real stdhead /
    | stdfoot from `include/functions.php`.
    |
    | Override in tests to point at lightweight shims so the assertions
    | don't depend on the full legacy include chain. The names MUST be
    | global functions; closures are not supported (the call site needs
    | to work even when invoked from within a `require`-ed legacy
    | script).
    |
    */

    'chrome' => [
        'head_function' => 'stdhead',
        'foot_function' => 'stdfoot',
    ],

];
