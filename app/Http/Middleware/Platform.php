<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class Platform
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (empty(CURRENT_PLATFORM)) {
            throw new \InvalidArgumentException("Require platform header.");
        }
        if (!in_array(CURRENT_PLATFORM, PLATFORMS)) {
            throw new \InvalidArgumentException("Invalid platform: " . CURRENT_PLATFORM);
        }
        return $next($request);
    }
}
