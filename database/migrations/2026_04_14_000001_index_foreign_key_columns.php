<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index every foreign key column.
 *
 * Postgres indexes the parent side of a foreign key but not the child, so a
 * declared relationship gives no index of its own. Two costs follow. Joins and
 * lookups by that column scan the table — the broker portal filtering leads by
 * broker_id, users scoped by tenant_id on every single request. And deleting a
 * parent row has to scan each referencing table to enforce the constraint, so
 * removing one tenant means a full scan of every table that cascades from it.
 *
 * Named after the column rather than the constraint so the same migration reads
 * the same on MySQL and SQLite, whose auto-generated constraint names differ.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string}> */
    protected array $foreignKeys = [
            ['api_credentials', 'created_by'],
            ['audit_log', 'user_id'],
            ['deal_buyer_matches', 'buyer_id'],
            ['deal_documents', 'deal_id'],
            ['deal_offers', 'deal_id'],
            ['deals', 'lead_id'],
            ['do_not_contact_list', 'added_by'],
            ['imports_log', 'list_id'],
            ['imports_log', 'user_id'],
            ['lead_client_photos', 'tenant_id'],
            ['lead_photos', 'tenant_id'],
            ['lead_photos', 'uploaded_by'],
            ['leads', 'broker_id'],
            ['leads', 'visited_property_id'],
            ['list_leads', 'lead_id'],
            ['list_leads', 'tenant_id'],
            ['lists', 'tenant_id'],
            ['open_house_attendees', 'lead_id'],
            ['open_house_attendees', 'open_house_id'],
            ['open_houses', 'agent_id'],
            ['open_houses', 'property_id'],
            ['plugins', 'tenant_id'],
            ['property_broker', 'assigned_by'],
            ['property_broker', 'user_id'],
            ['property_images', 'uploaded_by'],
            ['role_permission', 'permission_id'],
            ['saved_view_defaults', 'saved_view_id'],
            ['saved_views', 'user_id'],
            ['sequence_enrollments', 'lead_id'],
            ['sequence_enrollments', 'tenant_id'],
            ['sequence_steps', 'sequence_id'],
            ['showings', 'agent_id'],
            ['showings', 'deal_id'],
            ['showings', 'lead_id'],
            ['showings', 'property_id'],
            ['system_snapshots', 'tenant_id'],
            ['system_snapshots', 'user_id'],
            ['system_updates', 'tenant_id'],
            ['system_updates', 'user_id'],
            ['transaction_checklists', 'deal_id'],
            ['users', 'role_id'],
            ['users', 'tenant_id'],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $index = "{$table}_{$column}_index";

            if ($this->indexExists($table, $index)) {
                continue;
            }

            Schema::table($table, function ($blueprint) use ($column, $index) {
                $blueprint->index($column, $index);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeys as [$table, $column]) {
            $index = "{$table}_{$column}_index";

            if (Schema::hasTable($table) && $this->indexExists($table, $index)) {
                Schema::table($table, fn ($blueprint) => $blueprint->dropIndex($index));
            }
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'pgsql' => DB::table('pg_indexes')->where('tablename', $table)->where('indexname', $index)->exists(),
            'mysql' => DB::selectOne("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== null,
            default => collect(DB::select("PRAGMA index_list('{$table}')"))->contains(fn ($i) => $i->name === $index),
        };
    }
};
