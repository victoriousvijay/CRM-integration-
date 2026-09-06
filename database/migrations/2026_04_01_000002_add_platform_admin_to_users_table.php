<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform admins operate the CRM platform itself (creating/managing tenants)
     * and are distinct from tenant admins, who only manage their own organization.
     * A platform admin still belongs to a tenant row (users.tenant_id stays
     * NOT NULL), but the flag grants access to the platform admin area
     * regardless of which tenant that is.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
