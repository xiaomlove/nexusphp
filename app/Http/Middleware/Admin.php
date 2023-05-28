<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class Admin
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
        /** @var User $user */
        $user = $request->user();
        if (!$user || !$user->canAccessAdmin()) {
            do_log("denied!");
            throw new UnauthorizedException('Unauthorized!');
        }
        do_log("allow!");
        return $next($request);
    }
}
