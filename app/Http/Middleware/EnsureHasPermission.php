<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->isActive()) {
            abort(403, 'Account is suspended.');
        }

        if (! $user->hasPermission($permission)) {
            abort(403, 'Unauthorized access: missing required permission.');
        }

        return $next($request);
    }
}
