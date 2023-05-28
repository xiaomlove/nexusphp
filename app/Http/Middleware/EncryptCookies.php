<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        'c_secure_pass',
        'c_secure_uid',
        'c_secure_login',
        'c_secure_ssl',
        'c_secure_tracker_ssl',
        'c_lang_folder',
    ];
}
