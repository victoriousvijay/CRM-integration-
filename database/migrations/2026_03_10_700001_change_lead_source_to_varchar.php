<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite treats all string columns as TEXT — no ENUM restriction exists, so this is a no-op.
            return;
        }

        // Change lead_source from ENUM to VARCHAR to support custom/dynamic sources
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE leads ALTER COLUMN lead_source TYPE VARCHAR(100)");
            DB::statement("ALTER TABLE leads ALTER COLUMN lead_source SET DEFAULT 'other'");
        } else {
            DB::statement("ALTER TABLE leads MODIFY COLUMN lead_source VARCHAR(100) DEFAULT 'other'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_lead_source_check");
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_lead_source_check CHECK (lead_source IN ('cold_call','direct_mail','website','referral','driving_for_dollars','list_import','other'))");
            DB::statement("ALTER TABLE leads ALTER COLUMN lead_source SET DEFAULT 'other'");
        } else {
            DB::statement("ALTER TABLE leads MODIFY COLUMN lead_source ENUM('cold_call','direct_mail','website','referral','driving_for_dollars','list_import','other') DEFAULT 'other'");
        }
    }
};
