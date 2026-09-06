<?php

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use App\Models\Tenant;
use App\Services\ApiCredentialService;
use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function __construct(private ApiCredentialService $credentials) {}

    /**
     * Authenticate the full-access REST API (/api/v1/*) via the X-API-Key header.
     *
     * Accepts the modern "{prefix}.{secret}" credential format (recommended,
     * see Settings > Integrations) and, for backward compatibility, the
     * legacy single shared `tenants.api_key` value. Only "full" type
     * credentials may authenticate here — "embed" credentials are restricted
     * to the public lead-intake endpoint and are rejected below.
     */
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-Key');

        if (!$key) {
            return response()->json(['error' => 'API key required. Pass via X-API-Key header.'], 401);
        }

        $tenant = null;
        $credential = null;

        if (str_contains($key, '.')) {
            $credential = $this->credentials->resolve($key);

            if (!$credential) {
                return response()->json(['error' => 'Invalid or revoked API key.'], 403);
            }

            if (!$credential->isFull()) {
                return response()->json(['error' => 'This key is not authorized for the full API.'], 403);
            }

            $tenant = Tenant::find($credential->tenant_id);
        } else {
            // Legacy tenant-wide key (deprecated, kept for backward compatibility).
            $tenant = Tenant::where('api_key', $key)->where('api_enabled', true)->first();
        }

        if (!$tenant) {
            return response()->json(['error' => 'Invalid or disabled API key.'], 403);
        }

        if ($tenant->status !== 'active') {
            return response()->json(['error' => 'Account is suspended.'], 403);
        }

        if ($credential) {
            $this->credentials->touchLastUsed($credential);
            $request->attributes->set('api_credential', $credential);
        }

        // Store tenant on request for use in controllers. Tenant identity is
        // resolved solely from the verified key above — never from any
        // tenant_id supplied in the request body/query.
        $request->merge(['_tenant' => $tenant]);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
