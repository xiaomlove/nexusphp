<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    const TG_WEBHOOK_PREFIX = 'tg-webhook';

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        self::TG_WEBHOOK_PREFIX.'/*',
        'web/token/*',
        // Phase 2 legacy migration: the AJAX helper that posts to
        // `/thanks.php` (see `public/js/common.js#saythanks`) is a
        // plain XHR with no CSRF token plumbing. Adding token
        // plumbing to `common.js` is a separate, larger change
        // touching every form helper, so until then `/thanks.php`
        // keeps the same auth-required-but-CSRF-exempt posture it
        // had as a legacy PHP-FPM script.
        'thanks.php',
    ];
}
