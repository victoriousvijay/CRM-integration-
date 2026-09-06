<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Services\ApiCredentialService;
use Illuminate\Http\Request;

/**
 * Tenant-scoped management of API/embed credentials (Settings > Integrations).
 * Supports multiple named keys per tenant (e.g. "Production Website",
 * "Staging Website") instead of one shared secret.
 */
class ApiCredentialController extends Controller
{
    public function store(Request $request, ApiCredentialService $service)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:full,embed',
        ]);

        $tenant = auth()->user()->tenant;

        $result = $service->generate($tenant, $data['name'], $data['type'], auth()->id());

        if (!$tenant->api_enabled) {
            $tenant->update(['api_enabled' => true]);
        }

        AuditLog::log('settings.api_credential_created', $result['credential']);

        return redirect()
            ->route('settings.index', ['tab' => 'api'])
            ->with('success', 'API credential created.')
            ->with('provisioned_token', $result['token'])
            ->with('provisioned_credential_id', $result['credential']->id);
    }

    public function revoke(ApiCredential $credential, ApiCredentialService $service)
    {
        abort_unless($credential->tenant_id === auth()->user()->tenant_id, 403);

        $service->revoke($credential);

        AuditLog::log('settings.api_credential_revoked', $credential);

        return redirect()->route('settings.index', ['tab' => 'api'])->with('success', 'API credential revoked.');
    }
}
