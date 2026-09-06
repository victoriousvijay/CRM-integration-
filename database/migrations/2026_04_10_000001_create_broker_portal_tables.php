<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Broker portal: brokers are tenant users who see only the properties an
 * admin has shared with them, through a stripped-down portal rather than
 * the full CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Brokers are a system role like the other built-ins, so they exist
        // for every tenant without per-tenant seeding.
        if (! DB::table('roles')->where('name', 'broker')->whereNull('tenant_id')->exists()) {
            DB::table('roles')->insert([
                'tenant_id' => null,
                'name' => 'broker',
                'display_name' => 'Broker',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasColumn('properties', 'shared_with_all_brokers')) {
            Schema::table('properties', function (Blueprint $table) {
                // Distinct from listing every broker in the pivot: this keeps
                // the property visible to brokers added later, which is what
                // "share with all brokers" is normally understood to mean.
                $table->boolean('shared_with_all_brokers')->default(false)->after('listing_status');
            });
        }

        if (! Schema::hasTable('property_broker')) {
            Schema::create('property_broker', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();

                $table->unique(['property_id', 'user_id']);
                $table->index(['tenant_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_broker');

        if (Schema::hasColumn('properties', 'shared_with_all_brokers')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('shared_with_all_brokers');
            });
        }

        DB::table('roles')->where('name', 'broker')->whereNull('tenant_id')->delete();
    }
};
