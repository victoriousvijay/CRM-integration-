<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a moment in a lead's life to a template approved in Meta Business
 * Manager. Meta will not deliver a business-initiated WhatsApp message from
 * free text, so the CRM can only name a template and supply its variables.
 */
class WhatsappTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'event',
        'status',
        'template_name',
        'language_code',
        'variables',
        'preview',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
