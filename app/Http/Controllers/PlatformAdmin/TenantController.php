<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Platform-owner controls for onboarding and operating white-label clients
 * (tenants). Never reachable by tenant admins — gated by the
 * `platform.admin` middleware, see App\Http\Middleware\PlatformAdminMiddleware.
 */
class TenantController extends Controller
{
    /** Largest logo accepted, in kilobytes. */
    public const MAX_LOGO_KILOBYTES = 2048;

    /**
     * The per-client switches the platform owner controls.
     *
     * Every one of these is enforced somewhere in the app — see the migration
     * that added the last two. A switch here that gated nothing would be worse
     * than no switch, so keep this list and the enforcement in step.
     *
     * @var array<string, string>
     */
    public const FEATURES = [
        'api_enabled' => 'Website & API access',
        'broker_portal_enabled' => 'Broker portal',
        'buyer_portal_enabled' => 'Buyer portal',
        'ai_enabled' => 'AI features',
        'require_2fa' => 'Require two-factor authentication',
    ];

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
        $tenant->load('logo')->loadCount('users', 'leads', 'deals', 'buyers');
        $credentials = ApiCredential::where('tenant_id', $tenant->id)->orderByDesc('created_at')->get();

        return view('platform-admin.tenants.show', compact('tenant', 'credentials'));
    }

    public function edit(Tenant $tenant)
    {
        return view('platform-admin.tenants.edit', [
            'tenant' => $tenant->load('logo'),
            'features' => self::FEATURES,
        ]);
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
            // Blank means no limit, which is what most clients are on.
            'max_users' => 'nullable|integer|min:1|max:10000',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:'.self::MAX_LOGO_KILOBYTES,
            'remove_logo' => 'nullable|boolean',
        ], [
            'logo.max' => 'The logo must be '.round(self::MAX_LOGO_KILOBYTES / 1024).' MB or smaller.',
        ]);

        // Checkboxes: an unticked box sends nothing, so read every flag from the
        // known list rather than from whatever happened to arrive.
        foreach (array_keys(self::FEATURES) as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $tenant->update($data);

        if ($request->boolean('remove_logo')) {
            $tenant->logo()->delete();
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');

            // One logo per tenant, replaced rather than accumulated.
            $tenant->logo()->delete();
            $tenant->logo()->create([
                'uploaded_by' => auth()->id(),
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'content' => base64_encode(file_get_contents($file->getRealPath())),
            ]);
        }

        AuditLog::log('platform.tenant_updated', $tenant);

        return redirect()->route('platform-admin.tenants.show', $tenant)->with('success', 'Client updated.');
    }

    /**
     * Sign in as a client's admin, to see what they see.
     *
     * Separate from the tenant-level impersonation in SettingsController: that
     * one requires both accounts to be in the same tenant, which is exactly what
     * this crosses. It keeps its own session key so returning lands back on the
     * platform account rather than trying — and failing — to find a same-tenant
     * admin to return to.
     */
    public function signInAs(Request $request, Tenant $tenant)
    {
        $request->validate(['password' => 'required|string']);

        $platformAdmin = $request->user();

        if (! Hash::check($request->password, $platformAdmin->password)) {
            return back()->with('error', 'Incorrect password. Sign-in refused.');
        }

        $target = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $target) {
            return back()->with('error', 'This client has no active admin to sign in as.');
        }

        AuditLog::log('platform.signed_in_as', $target, null, [
            'platform_admin_id' => $platformAdmin->id,
            'tenant_id' => $tenant->id,
        ]);

        session(['platform_impersonating' => $platformAdmin->id]);
        Auth::login($target);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    /**
     * Return to the platform account after signing in as a client.
     */
    public function returnToPlatform(Request $request)
    {
        $platformAdminId = session('platform_impersonating');

        abort_if($platformAdminId === null, 403);

        $platformAdmin = User::withoutGlobalScopes()
            ->where('id', $platformAdminId)
            ->where('is_platform_admin', true)
            ->first();

        abort_if($platformAdmin === null, 403);

        AuditLog::log('platform.returned_from_sign_in_as', $platformAdmin);

        Auth::login($platformAdmin);
        $request->session()->forget('platform_impersonating');
        $request->session()->regenerate();

        return redirect()->route('platform-admin.tenants.index');
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
