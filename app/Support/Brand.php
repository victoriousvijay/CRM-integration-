<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves what brand the current request should be presented under.
 *
 * The platform is white-label: the same deployment serves many tenants, and
 * each one's users should see that tenant's name and logo, never the
 * platform's or another client's. Anything a tenant's users look at goes
 * through name()/logo(); only screens where the platform speaks as itself
 * (the installer, platform admin, update and licensing copy) use
 * platformName().
 */
class Brand
{
    /**
     * The tenant whose brand applies to this request, if any.
     */
    public static function tenant(): ?Tenant
    {
        $user = Auth::user();

        if ($user?->tenant) {
            return $user->tenant;
        }

        // Public endpoints (embedded lead forms, buyer portal, the lead
        // ingest API) resolve their tenant from a verified credential or
        // route binding rather than a session, and stash it here.
        $resolved = request()?->attributes->get('tenant');

        return $resolved instanceof Tenant ? $resolved : null;
    }

    /**
     * Brand name for the current request — the tenant's, or the platform's.
     */
    public static function name(): string
    {
        $tenantName = static::tenant()?->name;

        return filled($tenantName) ? $tenantName : static::platformName();
    }

    /**
     * The SaaS product's own name, independent of any tenant.
     */
    public static function platformName(): string
    {
        return (string) config('platform.name');
    }

    /**
     * The current tenant's uploaded logo, if they have one.
     *
     * Returns null rather than a platform logo on purpose: the shipped logo
     * images have a product name baked into the artwork, which is exactly
     * what a white-label deployment must not show. Callers fall back to
     * rendering name() as a wordmark instead — see layouts/_brand.blade.php.
     */
    public static function logo(): ?string
    {
        return static::logoFor(static::tenant());
    }

    /**
     * A specific tenant's logo URL, or null when they have none.
     *
     * The database copy wins. Logos used to be written to the `public` disk,
     * which a serverless host mounts read-only outside /tmp — so those uploads
     * were lost and the brand fell back to a wordmark. `logo_path` is still
     * honoured second so a deployment on a real disk keeps working.
     */
    public static function logoFor(?Tenant $tenant): ?string
    {
        if (! $tenant) {
            return null;
        }

        if ($tenant->logo) {
            return route('tenant.logo', ['tenant' => $tenant->getKey(), 'v' => $tenant->logo->updated_at?->timestamp]);
        }

        return filled($tenant->logo_path) ? asset('storage/'.$tenant->logo_path) : null;
    }

    /**
     * The platform's own logo — used where the product speaks as itself
     * (the sign-in screen, the "powered by" footer), never as a stand-in
     * for a tenant's brand.
     */
    public static function platformLogo(): string
    {
        return asset('images/platform-logo.png');
    }

    /**
     * The logo mark alone, for placements too small for the wordmark to read.
     */
    public static function platformMark(): string
    {
        return asset('images/platform-mark.png');
    }
}
