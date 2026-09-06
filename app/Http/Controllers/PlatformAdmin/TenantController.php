<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantOnboardingService;
use Illuminate\Http\Request;

/**
 * Platform-owner controls for onboarding and operating white-label clients
 * (tenants). Never reachable by tenant admins — gated by the
 * `platform.admin` middleware, see App\Http\Middleware\PlatformAdminMiddleware.
 */
class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withoutGlobalScopes()
            ->withCount('users')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('platform-admin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('platform-admin.tenants.create');
    }

    public function store(Request $request, TenantOnboardingService $onboarding)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|alpha_dash',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'business_mode' => 'nullable|in:wholesale,realestate',
            'country' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
        ]);

        $result = $onboarding->onboard($data);

        AuditLog::log('platform.tenant_created', $result['tenant']);

        return redirect()
            ->route('platform-admin.tenants.show', $result['tenant'])
            ->with('success', 'Client onboarded successfully.')
            ->with('provisioned_full_key', $result['full_key'])
            ->with('provisioned_embed_key', $result['embed_key']);
    }

    public function show(Tenant $tenant)
    {
        $tenant->loadCount('users', 'leads', 'deals', 'buyers');
        $credentials = ApiCredential::where('tenant_id', $tenant->id)->orderByDesc('created_at')->get();

        return view('platform-admin.tenants.show', compact('tenant', 'credentials'));
    }

    public function edit(Tenant $tenant)
    {
        return view('platform-admin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
            'business_mode' => 'nullable|in:wholesale,realestate',
        ]);

        $tenant->update($data);

        AuditLog::log('platform.tenant_updated', $tenant);

        return redirect()->route('platform-admin.tenants.show', $tenant)->with('success', 'Client updated.');
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => 'suspended']);
        AuditLog::log('platform.tenant_suspended', $tenant);

        return back()->with('success', 'Client suspended.');
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active']);
        AuditLog::log('platform.tenant_activated', $tenant);

        return back()->with('success', 'Client activated.');
    }
}
