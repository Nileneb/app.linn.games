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

        DB::statement('CREATE INDEX IF NOT EXISTS idx_projekte_workspace_user_erstellt ON projekte(workspace_id, user_id, erstellt_am DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_chat_messages_workspace_created ON chat_messages(workspace_id, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_p5_treffer_projekt_duplikat ON p5_treffer(projekt_id, ist_duplikat)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_p5_screening_treffer_entscheidung ON p5_screening_entscheidungen(treffer_id, entscheidung)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_p6_bewertung_treffer_urteil ON p6_qualitaetsbewertung(treffer_id, gesamturteil)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_p6_bewertung_treffer_urteil');
        DB::statement('DROP INDEX IF EXISTS idx_p5_screening_treffer_entscheidung');
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_projekt_duplikat');
        DB::statement('DROP INDEX IF EXISTS idx_chat_messages_workspace_created');
        DB::statement('DROP INDEX IF EXISTS idx_projekte_workspace_user_erstellt');
    }
};
