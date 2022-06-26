<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

class Filament extends Authenticate
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request): string
    {
        return getSchemeAndHttpHost() . '/login.php';
    }
}
