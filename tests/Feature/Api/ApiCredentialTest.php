<?php

namespace Tests\Feature\Api;

use App\Models\ApiCredential;
use App\Services\ApiCredentialService;
use Tests\TestCase;

class ApiCredentialTest extends TestCase
{
    public function test_full_credential_authenticates_the_rest_api(): void
    {
        $this->createTenantWithAdmin();
        ['token' => $token] = app(ApiCredentialService::class)->generate($this->tenant, 'Server Integration', ApiCredential::TYPE_FULL);

        $response = $this->getJson('/api/v1/leads', ['X-API-Key' => $token]);

        $response->assertStatus(200);
    }

    public function test_embed_credential_cannot_authenticate_the_full_rest_api(): void
    {
        $this->createTenantWithAdmin();
        ['token' => $token] = app(ApiCredentialService::class)->generate($this->tenant, 'Website', ApiCredential::TYPE_EMBED);

        $response = $this->getJson('/api/v1/leads', ['X-API-Key' => $token]);

        $response->assertStatus(403);
    }

    public function test_embed_credential_can_create_a_lead_via_the_public_endpoint(): void
    {
        $this->createTenantWithAdmin(['api_enabled' => true]);
        ['token' => $token] = app(ApiCredentialService::class)->generate($this->tenant, 'Website', ApiCredential::TYPE_EMBED);

        $response = $this->postJson('/api/v1/public/leads', [
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone' => '9876543210',
        ], ['X-Embed-Key' => $token]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenant->id,
            'phone' => '9876543210',
        ]);
    }

    public function test_revoked_credential_is_rejected(): void
    {
        $this->createTenantWithAdmin();
        $service = app(ApiCredentialService::class);
        ['credential' => $credential, 'token' => $token] = $service->generate($this->tenant, 'Old Key', ApiCredential::TYPE_FULL);
        $service->revoke($credential);

        $response = $this->getJson('/api/v1/leads', ['X-API-Key' => $token]);

        $response->assertStatus(403);
    }

    public function test_honeypot_field_silently_drops_spam_without_creating_a_lead(): void
    {
        $this->createTenantWithAdmin(['api_enabled' => true]);
        ['token' => $token] = app(ApiCredentialService::class)->generate($this->tenant, 'Website', ApiCredential::TYPE_EMBED);

        $response = $this->postJson('/api/v1/public/leads', [
            'first_name' => 'Bot',
            'last_name' => 'Spam',
            'website_url_confirm' => 'http://spam.example',
        ], ['X-Embed-Key' => $token]);

        $response->assertStatus(201);
        $this->assertDatabaseMissing('leads', ['first_name' => 'Bot']);
    }
}
