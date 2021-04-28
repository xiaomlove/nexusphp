<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class Permission
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
        $user = $request->user();
        $targetClass = User::CLASS_MODERATOR;
        $log = sprintf('user: %s, class: %s, target class: %s', $user->id, $user->class, $targetClass);
        if (!$user || $user->class < $targetClass) {
            do_log("$log, denied!");
            throw new UnauthorizedException('Unauthorized!');
        }
        do_log("$log, allow!");
        return $next($request);
    }
}
