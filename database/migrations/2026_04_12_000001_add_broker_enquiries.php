<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions that let a broker log a walk-in enquiry from their portal.
 *
 * - properties.map_url: a map link an admin can paste for a property, so
 *   everyone else gets a tappable address instead of copying it by hand.
 * - leads.broker_id / visited_property_id: records which broker brought the
 *   enquiry in and which property the client was actually shown, so the CRM
 *   can attribute it and the broker can see their own list.
 * - lead_client_photos: the client's own photo, kept in its own table rather
 *   than a column on leads so listing leads doesn't drag image bytes along.
 *   Distinct from the existing lead_photos, which holds disk-backed photos of
 *   a lead's property.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'map_url')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('map_url', 2048)->nullable()->after('zip_code');
            });
        }

        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'broker_id')) {
                $table->foreignId('broker_id')->nullable()->after('agent_id')
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('leads', 'visited_property_id')) {
                $table->foreignId('visited_property_id')->nullable()->after('broker_id')
                    ->constrained('properties')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('lead_client_photos')) {
            Schema::create('lead_client_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('filename');
                $table->string('mime_type', 100);
                $table->unsignedInteger('size_bytes');
                $table->longText('content');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_client_photos');

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'visited_property_id')) {
                $table->dropConstrainedForeignId('visited_property_id');
            }
            if (Schema::hasColumn('leads', 'broker_id')) {
                $table->dropConstrainedForeignId('broker_id');
            }
        });

        if (Schema::hasColumn('properties', 'map_url')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('map_url');
            });
        }
    }
};
