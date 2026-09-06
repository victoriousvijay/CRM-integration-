<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    const TYPE_FULL = 'full';
    const TYPE_EMBED = 'embed';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'key_prefix',
        'hashed_secret',
        'last_used_at',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isFull(): bool
    {
        return $this->type === self::TYPE_FULL;
    }

    public function isEmbed(): bool
    {
        return $this->type === self::TYPE_EMBED;
    }
}
