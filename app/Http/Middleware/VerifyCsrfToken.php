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
        // Phase 2 legacy migration: legacy XHR / form helpers post to
        // these URLs without sending a CSRF token (see
        // `public/js/common.js#saythanks`, the comment-edit "Preview"
        // button, and the staff Reports admin form). Adding CSRF
        // plumbing to every legacy form helper is a separate, larger
        // change; until then the migrated controllers keep the same
        // auth-required-but-CSRF-exempt posture they had as legacy
        // PHP-FPM scripts.
        'thanks.php',
        'preview.php',
        'takecontact.php',
        'takeupdate.php',
        // Phase 2 batch #2: `/logout.php` is `Route::any(...)` so POST
        // callers (some legacy XHRs) reach it without a CSRF token.
        // The other batch #2 endpoints are GET-only and not listed.
        'logout.php',
        // Phase 2 batch #4: `/clearcache.php` accepts POST without a
        // CSRF token (the legacy moderator form has no `@csrf` field).
        // `/smilies.php` and `/allagents.php` are GET-only.
        'clearcache.php',
        // Phase 2 batch #5: `/donated.php` accepts POST without a
        // CSRF token (the legacy sysop form has no `@csrf` field).
        // `/contactstaff.php` and `/takeflush.php` are GET-only.
        'donated.php',
    ];
}
