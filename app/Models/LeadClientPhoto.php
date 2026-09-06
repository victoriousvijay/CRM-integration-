<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo of the client on a lead, kept out of the leads table so listing
 * leads never pulls image bytes with it.
 */
class LeadClientPhoto extends Model
{
    protected $table = 'lead_client_photos';

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'filename',
        'mime_type',
        'size_bytes',
        'content',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function bytes(): string
    {
        return base64_decode($this->content, true) ?: '';
    }

    public function url(): string
    {
        return route('leads.photo', $this->lead_id);
    }
}
