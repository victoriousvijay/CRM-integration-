<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Gate a route on what the user may do, not on what their role is called.
     *
     * The `role:` middleware lists role names, which is why a role a tenant
     * created could never reach anything: "Agency Owner" is in nobody's list,
     * so every module answered 403 no matter which permissions its creator had
     * switched on. A permission key is the same question asked in a way a
     * custom role can answer.
     *
     * Several keys mean any one of them is enough.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        // A broker is not short of a permission — they are in the wrong product.
        // Same courtesy as RoleMiddleware: send them to their own portal rather
        // than to a wall they cannot act on.
        if ($user->isBroker() && ! $request->expectsJson()) {
            return redirect()->route('broker.index');
        }

        abort(403, 'Unauthorized action.');
    }
}
