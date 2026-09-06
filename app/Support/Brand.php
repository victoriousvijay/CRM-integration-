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
     * Logo URL for the current request.
     *
     * @param  bool  $onDark  Whether the logo sits on a dark background, which
     *                        the platform's own logo has a variant for.
     */
    public static function logo(bool $onDark = false): string
    {
        $logoPath = static::tenant()?->logo_path;

        if (filled($logoPath)) {
            return asset('storage/'.$logoPath);
        }

        return asset($onDark ? 'images/logo-white.png' : 'images/logo.png');
    }
}
