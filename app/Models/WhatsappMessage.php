<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * One attempt to send a WhatsApp message, kept whether it succeeded or not so
 * an admin can see what a client was actually told.
 */
class WhatsappMessage extends Model
{
    protected $fillable = [
        'tenant_id',
        'lead_id',
        'to_number',
        'template_name',
        'event',
        'status',
        'provider_message_id',
        'error',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
