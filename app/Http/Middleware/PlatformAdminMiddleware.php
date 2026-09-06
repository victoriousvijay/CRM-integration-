<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminMiddleware
{
    /**
     * Restrict the /platform-admin area to users flagged as platform admins.
     * Tenant admins (even tenant "admin" role) never pass this check — the
     * platform layer is operated by the CRM platform owner, not a client.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->is_platform_admin) {
            abort(403, 'Platform admin access required.');
        }

        return $next($request);
    }
}
