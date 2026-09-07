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
    /**
     * Everything except the base64 bytes — see PropertyImage::METADATA_COLUMNS.
     *
     * @var list<string>
     */
    public const METADATA_COLUMNS = [
        'id', 'tenant_id', 'lead_id', 'filename', 'mime_type',
        'size_bytes', 'created_at', 'updated_at',
    ];

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
        $content = $this->content ?? static::whereKey($this->getKey())->value('content');

        return base64_decode((string) $content, true) ?: '';
    }

    public function url(): string
    {
        return route('leads.photo', $this->lead_id);
    }
}
