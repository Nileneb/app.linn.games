<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Ensure langdock_agent role exists (idempotent)
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'langdock_agent') THEN
                    CREATE ROLE langdock_agent LOGIN PASSWORD 'temporary_password_for_tests';
                END IF;
            END
            $$;
        SQL);

        // Erstelle RLS-Policy für chat_messages auf workspace-level Zugriff
        // (falls chat_messages noch keine workspace_level Policy hat)
        DB::statement(<<<'SQL'
            DROP POLICY IF EXISTS "mcp_workspace_scope" ON chat_messages;
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY mcp_workspace_scope ON chat_messages
                FOR ALL
                TO langdock_agent
                USING (
                    workspace_id = NULLIF(current_setting('app.current_workspace_id', true), '')::uuid
                    OR workspace_id = (
                        SELECT workspace_id FROM projekte
                        WHERE id = NULLIF(current_setting('app.current_projekt_id', true), '')::uuid
                        LIMIT 1
                    )
                )
                WITH CHECK (
                    workspace_id = NULLIF(current_setting('app.current_workspace_id', true), '')::uuid
                    OR workspace_id = (
                        SELECT workspace_id FROM projekte
                        WHERE id = NULLIF(current_setting('app.current_projekt_id', true), '')::uuid
                        LIMIT 1
                    )
                );
        SQL);

        // Für projekte: Allow access zu allen Projekten im aktuellen Workspace
        DB::statement(<<<'SQL'
            DROP POLICY IF EXISTS "mcp_workspace_scope" ON projekte;
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY mcp_workspace_scope ON projekte
                FOR ALL
                TO langdock_agent
                USING (
                    workspace_id = NULLIF(current_setting('app.current_workspace_id', true), '')::uuid
                    OR id = NULLIF(current_setting('app.current_projekt_id', true), '')::uuid
                )
                WITH CHECK (
                    workspace_id = NULLIF(current_setting('app.current_workspace_id', true), '')::uuid
                    OR id = NULLIF(current_setting('app.current_projekt_id', true), '')::uuid
                );
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS "mcp_workspace_scope" ON chat_messages;');
        DB::statement('DROP POLICY IF EXISTS "mcp_workspace_scope" ON projekte;');
    }
};
