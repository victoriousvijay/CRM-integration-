<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Check if the authenticated user has one of the required roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // A broker is not short of a privilege — they are in the wrong product.
        // Their whole account is the property portal, so a CRM URL (a bookmark,
        // a link someone pasted, the back button after switching accounts) is a
        // dead end: an Access Denied page offering them a Dashboard they also
        // cannot open. Send them home instead.
        if ($user->isBroker() && ! $request->expectsJson()) {
            return redirect()->route('broker.index');
        }

        abort(403, 'Unauthorized action.');
    }
}
