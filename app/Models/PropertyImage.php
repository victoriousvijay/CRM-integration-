<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
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
        return base64_decode($this->content, true) ?: '';
    }

    /**
     * URL the browser fetches this image from.
     */
    public function url(): string
    {
        return route('properties.images.show', $this);
    }
}
