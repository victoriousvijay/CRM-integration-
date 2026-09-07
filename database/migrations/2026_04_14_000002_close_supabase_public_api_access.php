<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shut the Supabase REST API out of the CRM's tables.
 *
 * Supabase exposes every table in the public schema through PostgREST and, by
 * default, grants the `anon` and `authenticated` roles full rights on anything
 * created there. The anon key that authenticates as `anon` is public by design
 * — it ships in browsers. So on a fresh Supabase deployment, with row level
 * security off, anyone holding that key could read every lead, every user row
 * and every stored credential over HTTP, and could delete or truncate them.
 *
 * This CRM never uses PostgREST or the Supabase client libraries; Laravel
 * connects straight to Postgres as the tables' owner. So the fix is to take the
 * access away rather than write a policy per table:
 *
 *   1. Revoke everything from both roles, including on the schema itself.
 *   2. Change default privileges so a later migration cannot silently reopen it.
 *   3. Enable row level security as a second line: with no policies, a
 *      non-owner role reads nothing even if a grant reappears. Postgres exempts
 *      table owners from RLS unless FORCE is set, so the app is unaffected.
 *
 * Postgres-only, and a no-op where those roles don't exist (a self-hosted
 * install, or the SQLite used by the tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if ($this->rolesPresent()) {
            DB::unprepared(<<<'SQL'
                REVOKE ALL ON ALL TABLES IN SCHEMA public FROM anon, authenticated;
                REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated;
                REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM anon, authenticated;
                REVOKE USAGE ON SCHEMA public FROM anon, authenticated;

                ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM anon, authenticated;
                ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM anon, authenticated;
                ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON FUNCTIONS FROM anon, authenticated;
            SQL);
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE t text;
            BEGIN
                FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public'
                LOOP
                    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
                END LOOP;
            END $$;
        SQL);
    }

    public function down(): void
    {
        // Deliberately not reversed. Restoring public API access to tables of
        // client records is not something a rollback should do quietly.
    }

    protected function rolesPresent(): bool
    {
        return DB::table('pg_roles')->whereIn('rolname', ['anon', 'authenticated'])->count() === 2;
    }
};
