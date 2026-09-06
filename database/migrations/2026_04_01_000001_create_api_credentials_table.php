<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-scoped API credentials. Replaces the single shared `tenants.api_key`
     * column with named, revocable, hashed credentials so a tenant can issue
     * one key per website/integration without sharing a single secret.
     *
     * `type` controls what the credential may be used for:
     *  - full  : the whole /api/v1 REST surface (leads, deals, buyers, properties, activities, stats)
     *  - embed : lead-creation only, safe to place in public/browser-facing code
     *            (embeddable form, embed.js widget)
     */
    public function up(): void
    {
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20)->default('full'); // full | embed
            $table->string('key_prefix', 16)->unique();
            $table->string('hashed_secret', 100);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
