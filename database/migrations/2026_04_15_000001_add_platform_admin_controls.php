<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the platform owner can decide per client.
 *
 * The tenant table already carried flags the app enforces (api_enabled,
 * ai_enabled, buyer_portal_enabled, require_2fa) but nothing let the platform
 * owner set them — a client got whatever the defaults were. These two are the
 * missing pieces of the same idea: whether a client gets the broker portal at
 * all, and how many people they may put on the account.
 *
 * Tenant logos move into the database. They were written to the `public` disk,
 * which on a serverless host is a read-only filesystem outside /tmp — so an
 * uploaded logo was lost the moment the function that received it exited, and
 * every white-label client silently fell back to a text wordmark. Property and
 * client photos already live in the database for this reason; this follows them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('broker_portal_enabled')->default(true)->after('buyer_portal_enabled');
            // Null means no limit, which is what every existing client had.
            $table->unsignedInteger('max_users')->nullable()->after('broker_portal_enabled');
        });

        Schema::create('tenant_logos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            // Base64 of the file. `text` rather than `binary` so the same schema
            // works on Postgres, MySQL and the SQLite the tests run on.
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_logos');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['broker_portal_enabled', 'max_users']);
        });
    }
};
