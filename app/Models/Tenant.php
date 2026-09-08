<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $hidden = [
        'api_key',
        'ai_api_key',
        'mail_settings',
        'whatsapp_settings',
    ];

    protected $fillable = [
        'name',
        'business_mode',
        'slug',
        'email',
        'logo_path',
        'timezone',
        'currency',
        'date_format',
        'country',
        'measurement_system',
        'locale',
        'distribution_method',
        'claim_window_minutes',
        'round_robin_index',
        'timezone_restriction_enabled',
        'custom_lead_sources',
        'custom_options',
        'status',
        'api_key',
        'api_enabled',
        'ai_enabled',
        'buyer_portal_enabled',
        'broker_portal_enabled',
        'enabled_modules',
        'max_users',
        'buyer_portal_headline',
        'buyer_portal_description',
        'buyer_portal_config',
        'ai_provider',
        'ai_api_key',
        'ai_model',
        'ai_ollama_url',
        'ai_custom_url',
        'ai_briefings_enabled',
        'notification_preferences',
        'default_dashboard_widgets',
        'mail_settings',
        'whatsapp_settings',
        'require_2fa',
        'sso_default_driver',
        'storage_disk',
    ];

    protected function casts(): array
    {
        return [
            'timezone_restriction_enabled' => 'boolean',
            'custom_lead_sources' => 'array',
            'custom_options' => 'array',
            'api_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'ai_briefings_enabled' => 'boolean',
            'ai_api_key' => 'encrypted',
            'buyer_portal_enabled' => 'boolean',
            'broker_portal_enabled' => 'boolean',
            'enabled_modules' => 'array',
            'max_users' => 'integer',
            'buyer_portal_config' => 'array',
            'notification_preferences' => 'array',
            'default_dashboard_widgets' => 'array',
            'mail_settings' => 'array',
            'whatsapp_settings' => 'array',
            'require_2fa' => 'boolean',
        ];
    }

    /**
     * Check if the tenant wants a specific notification type.
     * Defaults to true if the key is missing from preferences.
     */
    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];

        return $prefs[$type] ?? true;
    }

    public function isWholesale(): bool
    {
        return ($this->business_mode ?? 'wholesale') === 'wholesale';
    }

    public function isRealEstate(): bool
    {
        return $this->business_mode === 'realestate';
    }

    /**
     * The parts of the product a plan can include or leave out.
     *
     * Each module owns the permission groups listed against it: switching a
     * module off makes every permission in those groups answer no, whatever the
     * client's own roles say. The groups not listed here — `profile` and
     * `settings` — are never sold separately; a client admin always needs to
     * reach their own team and roles.
     *
     * @var array<string, array{label: string, groups: list<string>}>
     */
    public const MODULES = [
        'leads' => ['label' => 'Leads', 'groups' => ['leads']],
        'properties' => ['label' => 'Properties, listings, showings & open houses', 'groups' => ['properties']],
        'deals' => ['label' => 'Pipeline & transactions', 'groups' => ['deals']],
        'buyers' => ['label' => 'Clients & buyer database', 'groups' => ['buyers']],
        'calendar' => ['label' => 'Calendar', 'groups' => ['calendar']],
        'marketing' => ['label' => 'Marketing — sequences, lists, campaigns, tags', 'groups' => ['sequences', 'lists', 'tags']],
        'reports' => ['label' => 'Reports & insights', 'groups' => ['reports']],
    ];

    /**
     * Does this client's plan include the given module?
     *
     * Null means everything, which is what a client created before plans
     * existed is on — they keep what they had.
     */
    public function hasModule(string $module): bool
    {
        if ($this->enabled_modules === null) {
            return true;
        }

        return in_array($module, $this->enabled_modules, true);
    }

    /**
     * The module a permission key belongs to, or null when it belongs to none
     * and is therefore always available.
     */
    public static function moduleForPermission(string $key): ?string
    {
        $group = str_contains($key, '.') ? strstr($key, '.', true) : $key;

        foreach (static::MODULES as $module => $definition) {
            if (in_array($group, $definition['groups'], true)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * This tenant's logo. Selected without its bytes — see TenantLogo.
     */
    public function logo()
    {
        return $this->hasOne(TenantLogo::class)->select(TenantLogo::METADATA_COLUMNS);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }

    public function buyers()
    {
        return $this->hasMany(Buyer::class);
    }
}
