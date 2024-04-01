<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    const TG_WEBHOOK_PREFIX = "tg-webhook";
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        self::TG_WEBHOOK_PREFIX . "/*"
    ];
}
