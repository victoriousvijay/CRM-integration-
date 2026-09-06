<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\ApiCredentialService;
use Closure;
use Illuminate\Http\Request;

class EmbedKeyMiddleware
{
    public function __construct(private ApiCredentialService $credentials) {}

    /**
     * Authenticate the public, lead-creation-only embed endpoint used by the
     * embeddable form and embed.js widget. Accepts an "embed" type credential
     * (safe to place in browser-facing code) or, for flexibility, a "full"
     * credential — but never the raw tenant record and never anything that
     * grants read access to existing data.
     */
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Embed-Key') ?: $request->query('key');

        if (!$key) {
            return response()->json(['error' => 'Embed key required.'], 401);
        }

        $credential = $this->credentials->resolve($key);

        if (!$credential) {
            return response()->json(['error' => 'Invalid or revoked embed key.'], 403);
        }

        $tenant = Tenant::find($credential->tenant_id);

        if (!$tenant || $tenant->status !== 'active' || !$tenant->api_enabled) {
            return response()->json(['error' => 'This form is not currently accepting submissions.'], 403);
        }

        $this->credentials->touchLastUsed($credential);

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('api_credential', $credential);

        return $next($request);
    }
}
