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
        $platform = nexus()->getPlatform();
        if (empty($platform)) {
            throw new \InvalidArgumentException("Require platform header.");
        }
        if (!nexus()->isPlatformValid()) {
            throw new \InvalidArgumentException("Invalid platform: " . $platform);
        }
        return $next($request);
    }
}
