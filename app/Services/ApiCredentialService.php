<?php

namespace App\Services;

use App\Models\ApiCredential;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Issues and verifies tenant API credentials.
 *
 * A credential's plaintext token is only ever shown once, at creation time,
 * in the shape "{prefix}.{secret}". Only a SHA-256 hash of the secret is
 * stored, so a leaked database dump never exposes usable keys.
 */
class ApiCredentialService
{
    /**
     * Create a new credential and return it alongside the one-time plaintext token.
     *
     * @return array{credential: ApiCredential, token: string}
     */
    public function generate(Tenant $tenant, string $name, string $type = ApiCredential::TYPE_FULL, ?int $createdBy = null): array
    {
        $prefix = Str::lower(Str::random(10));
        $secret = Str::random(40);

        $credential = ApiCredential::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'type' => $type,
            'key_prefix' => $prefix,
            'hashed_secret' => hash('sha256', $secret),
            'created_by' => $createdBy,
        ]);

        return [
            'credential' => $credential,
            'token' => "{$prefix}.{$secret}",
        ];
    }

    /**
     * Resolve a raw "{prefix}.{secret}" token to its active, unrevoked credential.
     */
    public function resolve(string $rawToken): ?ApiCredential
    {
        if (! str_contains($rawToken, '.')) {
            return null;
        }

        [$prefix, $secret] = explode('.', $rawToken, 2);

        $credential = ApiCredential::where('key_prefix', $prefix)->whereNull('revoked_at')->first();

        if (! $credential || ! hash_equals($credential->hashed_secret, hash('sha256', $secret))) {
            return null;
        }

        return $credential;
    }

    public function revoke(ApiCredential $credential): void
    {
        $credential->update(['revoked_at' => now()]);
    }

    public function touchLastUsed(ApiCredential $credential): void
    {
        $credential->timestamps = false;
        $credential->update(['last_used_at' => now()]);
    }
}
