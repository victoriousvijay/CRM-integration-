<?php

namespace App\Services;

use App\Models\ApiCredential;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisions a brand new tenant (white-label client) end to end: the tenant
 * record and branding, its first admin user, and the default API/embed
 * credentials it needs to start receiving website leads immediately.
 *
 * Used by the platform admin "create client" flow (Settings > Clients) so a
 * new real estate company can be onboarded in minutes, not hours.
 */
class TenantOnboardingService
{
    public function __construct(private ApiCredentialService $credentials) {}

    /**
     * @param array{
     *   company_name: string,
     *   slug?: string,
     *   admin_name: string,
     *   admin_email: string,
     *   admin_password: string,
     *   country?: string,
     *   currency?: string,
     *   timezone?: string,
     *   business_mode?: string,
     *   logo_path?: string,
     * } $payload
     * @return array{tenant: Tenant, admin: User, full_key: string, embed_key: string}
     */
    public function onboard(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $slug = Str::slug($payload['slug'] ?? $payload['company_name']) ?: Str::random(8);

            $tenant = Tenant::create([
                'name' => $payload['company_name'],
                'slug' => $this->uniqueSlug($slug),
                'email' => $payload['admin_email'],
                'business_mode' => $payload['business_mode'] ?? 'realestate',
                'country' => $payload['country'] ?? null,
                'currency' => $payload['currency'] ?? 'USD',
                'timezone' => $payload['timezone'] ?? 'UTC',
                'date_format' => $payload['date_format'] ?? 'm/d/Y',
                'locale' => $payload['locale'] ?? 'en',
                'logo_path' => $payload['logo_path'] ?? null,
                'status' => 'active',
                'api_enabled' => true,
            ]);

            $adminRole = Role::where('name', 'admin')->firstOrFail();

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole->id,
                'name' => $payload['admin_name'],
                'email' => $payload['admin_email'],
                'password' => Hash::make($payload['admin_password']),
                'is_active' => true,
                'onboarding_completed' => false,
            ]);

            $full = $this->credentials->generate($tenant, 'Default Production API', ApiCredential::TYPE_FULL, $admin->id);
            $embed = $this->credentials->generate($tenant, 'Website Lead Form', ApiCredential::TYPE_EMBED, $admin->id);

            $this->seedDefaultLeadSources($tenant);

            return [
                'tenant' => $tenant,
                'admin' => $admin,
                'full_key' => $full['token'],
                'embed_key' => $embed['token'],
            ];
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while (Tenant::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function seedDefaultLeadSources(Tenant $tenant): void
    {
        $tenant->update([
            'custom_lead_sources' => [
                'Website', 'Google Ads', 'Meta Ads', 'WhatsApp', 'Referral',
                'Walk-in', 'Phone', 'Email', 'API', 'Property Portal', 'Manual', 'Other',
            ],
        ]);
    }
}
