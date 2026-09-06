<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic WhatsApp messages to a lead as their status moves.
 *
 * Settings live per tenant, not per platform: each client sends from their own
 * WhatsApp Business number under their own Meta account, and one client's
 * credentials must never send on another's behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'whatsapp_settings')) {
            Schema::table('tenants', function (Blueprint $table) {
                // { enabled, provider, phone_number_id, access_token (encrypted),
                //   business_account_id, default_language }
                $table->json('whatsapp_settings')->nullable()->after('mail_settings');
            });
        }

        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

                // Which moment sends this: 'lead.created', or 'lead.status_changed'
                // paired with the status that was moved into.
                $table->string('event', 50);
                $table->string('status', 50)->nullable();

                // The template as approved in Meta Business Manager. Meta will
                // not deliver business-initiated messages any other way.
                $table->string('template_name');
                $table->string('language_code', 10)->default('en');

                // Ordered placeholders for the template's body ({{1}}, {{2}}...),
                // named from what WhatsAppService::resolveVariable() understands.
                $table->json('variables')->nullable();

                $table->text('preview')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'event', 'status']);
            });
        }

        if (! Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('to_number', 30);
                $table->string('template_name')->nullable();
                $table->string('event', 50)->nullable();
                $table->string('status', 20); // sent | failed | skipped
                $table->string('provider_message_id')->nullable();
                $table->text('error')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index('lead_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_templates');

        if (Schema::hasColumn('tenants', 'whatsapp_settings')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('whatsapp_settings');
            });
        }
    }
};
