<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which parts of the product a client's plan includes.
 *
 * There are now two questions, asked by two different people, and they were
 * getting muddled into one:
 *
 *   1. What did this client buy?          — the platform owner decides, here.
 *   2. Who inside that client may use it? — their admin decides, per role.
 *
 * Effective access is both: a module the plan does not include is invisible to
 * everyone at that client, however generously their admin sets a role; and a
 * module the plan includes is still only open to the roles they grant it to.
 *
 * Null means every module, which is what every existing client is on — nobody
 * loses anything by this migration landing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('enabled_modules')->nullable()->after('broker_portal_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('enabled_modules');
        });
    }
};
