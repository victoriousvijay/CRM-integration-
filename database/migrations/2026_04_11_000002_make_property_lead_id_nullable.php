<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Properties don't always belong to a lead.
 *
 * The column was created NOT NULL back when every property was recorded
 * against a seller lead, but the field scout submission and the standalone
 * Properties screen both create properties with no lead at all — the insert
 * failed outright. The code already treats a null lead_id as meaningful
 * (PropertyPolicy::view and the delete rule both branch on it); only the
 * schema disagreed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => DB::statement('ALTER TABLE properties ALTER COLUMN lead_id DROP NOT NULL'),
            'mysql' => DB::statement('ALTER TABLE properties MODIFY lead_id BIGINT UNSIGNED NULL'),
            // SQLite can't alter nullability in place; its test schema is
            // rebuilt from migrations, where the column is already nullable.
            default => null,
        };
    }

    public function down(): void
    {
        // Deliberately not reversed: rows with no lead would have to be
        // deleted or invented to restore the constraint.
    }
};
