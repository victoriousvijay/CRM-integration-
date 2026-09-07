<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's logo, stored in the database.
 *
 * Deliberately not on a disk: the platform runs serverless, where the
 * filesystem outside /tmp is read-only and /tmp does not survive the request.
 * A logo written there vanished, and the white-label brand quietly reverted to
 * a text wordmark. PropertyImage and LeadClientPhoto solve the same problem the
 * same way.
 *
 * No TenantScope: this row *is* the tenant's, and the platform owner edits it
 * from the platform admin console, outside any tenant context.
 */
class TenantLogo extends Model
{
    /**
     * Everything except the bytes.
     *
     * `content` holds the whole file base64-encoded; a page that only needs to
     * render a URL must not drag it into memory.
     *
     * @var list<string>
     */
    public const METADATA_COLUMNS = [
        'id', 'tenant_id', 'uploaded_by', 'filename', 'mime_type', 'size_bytes',
        'created_at', 'updated_at',
    ];

    protected $fillable = [
        'tenant_id',
        'uploaded_by',
        'filename',
        'mime_type',
        'size_bytes',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The decoded image bytes, fetched on demand.
     */
    public function bytes(): string
    {
        $content = $this->content ?? static::whereKey($this->getKey())->value('content');

        return base64_decode((string) $content, true) ?: '';
    }
}
