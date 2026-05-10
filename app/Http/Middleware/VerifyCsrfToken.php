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
        'takeupdate.php',
    ];
}
