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

        $role = env('LANGDOCK_DB_USERNAME', 'mcp_agent');

        // 1. Tables with a direct projekt_id column
        foreach ($this->directProjektTables() as $table) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY mcp_projekt_scope ON \"{$table}\"
                AS PERMISSIVE FOR ALL TO \"{$role}\"
                USING (projekt_id = current_setting('app.current_projekt_id', true)::uuid)
                WITH CHECK (projekt_id = current_setting('app.current_projekt_id', true)::uuid)
            ");
        }

        // 2. Tables linked via treffer_id → p5_treffer.projekt_id
        foreach (['p5_screening_entscheidungen', 'p6_qualitaetsbewertung', 'p7_datenextraktion'] as $table) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY mcp_projekt_scope ON \"{$table}\"
                AS PERMISSIVE FOR ALL TO \"{$role}\"
                USING (
                    EXISTS (
                        SELECT 1 FROM p5_treffer
                        WHERE p5_treffer.id = treffer_id
                          AND p5_treffer.projekt_id = current_setting('app.current_projekt_id', true)::uuid
                    )
                )
                WITH CHECK (
                    EXISTS (
                        SELECT 1 FROM p5_treffer
                        WHERE p5_treffer.id = treffer_id
                          AND p5_treffer.projekt_id = current_setting('app.current_projekt_id', true)::uuid
                    )
                )
            ");
        }

        // 3. p4_anpassungsprotokoll linked via suchstring_id → p4_suchstrings.projekt_id
        DB::statement('ALTER TABLE "p4_anpassungsprotokoll" ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE "p4_anpassungsprotokoll" FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY mcp_projekt_scope ON \"p4_anpassungsprotokoll\"
            AS PERMISSIVE FOR ALL TO \"{$role}\"
            USING (
                EXISTS (
                    SELECT 1 FROM p4_suchstrings
                    WHERE p4_suchstrings.id = suchstring_id
                      AND p4_suchstrings.projekt_id = current_setting('app.current_projekt_id', true)::uuid
                )
            )
            WITH CHECK (
                EXISTS (
                    SELECT 1 FROM p4_suchstrings
                    WHERE p4_suchstrings.id = suchstring_id
                      AND p4_suchstrings.projekt_id = current_setting('app.current_projekt_id', true)::uuid
                )
            )
        ");

        // 4. chat_messages scoped via workspace_id → projekte.workspace_id for current projekt
        DB::statement('ALTER TABLE "chat_messages" ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE "chat_messages" FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY mcp_projekt_scope ON \"chat_messages\"
            AS PERMISSIVE FOR ALL TO \"{$role}\"
            USING (
                workspace_id = (
                    SELECT workspace_id FROM projekte
                    WHERE id = current_setting('app.current_projekt_id', true)::uuid
                )
            )
            WITH CHECK (
                workspace_id = (
                    SELECT workspace_id FROM projekte
                    WHERE id = current_setting('app.current_projekt_id', true)::uuid
                )
            )
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tablesToReset = array_merge(
            $this->directProjektTables(),
            ['p5_screening_entscheidungen', 'p6_qualitaetsbewertung', 'p7_datenextraktion'],
            ['p4_anpassungsprotokoll', 'chat_messages'],
        );

        foreach ($tablesToReset as $table) {
            DB::statement("DROP POLICY IF EXISTS mcp_projekt_scope ON \"{$table}\"");
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }
    }

    /** Tables that carry a direct projekt_id FK. */
    private function directProjektTables(): array
    {
        return [
            'p8_update_plan',
            'paper_embeddings',
            'papers',
        ];
    }
};
