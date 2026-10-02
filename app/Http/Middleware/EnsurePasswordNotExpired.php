<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustResetPassword()) {
            if (! $request->routeIs('password.force-reset', 'password.force-reset.update', 'logout')) {
                return redirect()->route('password.force-reset');
            }
        }

        return $next($request);
    }
}
