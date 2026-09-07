<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    /**
     * Everything about an image except its bytes.
     *
     * `content` holds the whole file base64-encoded, so selecting it to render
     * a thumbnail URL would pull megabytes per row into memory for a list of
     * properties. Relations load these columns instead and bytes() fetches the
     * content only when something actually serves the file.
     *
     * @var list<string>
     */
    public const METADATA_COLUMNS = [
        'id', 'tenant_id', 'property_id', 'uploaded_by',
        'filename', 'mime_type', 'size_bytes', 'sort_order', 'is_primary',
        'created_at', 'updated_at',
    ];

    protected $fillable = [
        'tenant_id',
        'property_id',
        'uploaded_by',
        'filename',
        'mime_type',
        'size_bytes',
        'content',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The decoded image bytes.
     */
    public function bytes(): string
    {
        // Loaded on demand: relations deliberately leave `content` out.
        $content = $this->content ?? static::whereKey($this->getKey())->value('content');

        return base64_decode((string) $content, true) ?: '';
    }

    /**
     * URL the browser fetches this image from.
     */
    public function url(): string
    {
        return route('properties.images.show', $this);
    }
}
