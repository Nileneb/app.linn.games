<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $roleName;

    public function __construct()
    {
        $this->roleName = env('LANGDOCK_DB_USERNAME', 'mcp_agent');
    }

    public function up(): void
    {
        $role     = $this->roleName;
        $password = env('LANGDOCK_DB_PASSWORD', '');
        $database = config('database.connections.pgsql.database');
        $pdo      = DB::getPdo();

        // 1. Dedicated DB role for MCP (no SUPERUSER, no BYPASSRLS)
        $quotedPassword = $pdo->quote($password);
        DB::statement("DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '{$role}') THEN
                    EXECUTE format('CREATE ROLE %I WITH LOGIN PASSWORD %s NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS', '{$role}', {$quotedPassword});
                END IF;
            END
        \$\$");

        DB::statement("GRANT CONNECT ON DATABASE \"{$database}\" TO \"{$role}\"");
        DB::statement("GRANT USAGE ON SCHEMA public TO \"{$role}\"");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO \"{$role}\"");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO \"{$role}\"");

        // 2. RLS on all tables scoped via projekt_id session variable
        foreach ($this->projektTables() as $table) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY mcp_projekt_scope ON \"{$table}\"
                AS PERMISSIVE FOR ALL TO \"{$role}\"
                USING ({$this->projektColumn($table)} = current_setting('app.current_projekt_id', true)::uuid)
                WITH CHECK ({$this->projektColumn($table)} = current_setting('app.current_projekt_id', true)::uuid)
            ");
        }
    }

    public function down(): void
    {
        $role     = $this->roleName;
        $database = config('database.connections.pgsql.database');

        foreach ($this->projektTables() as $table) {
            DB::statement("DROP POLICY IF EXISTS mcp_projekt_scope ON \"{$table}\"");
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }

        DB::statement("REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM \"{$role}\"");
        DB::statement("REVOKE ALL PRIVILEGES ON SCHEMA public FROM \"{$role}\"");
        DB::statement("REVOKE CONNECT ON DATABASE \"{$database}\" FROM \"{$role}\"");
        DB::statement("DROP ROLE IF EXISTS \"{$role}\"");
    }

    /** Tables scoped to a projekt_id via session variable. */
    private function projektTables(): array
    {
        return [
            'projekte',
            'phasen',
            'p1_strukturmodell_wahl',
            'p1_komponenten',
            'p1_kriterien',
            'p1_warnsignale',
            'p2_review_typ_entscheidung',
            'p2_mapping_suchstring_komponenten',
            'p2_trefferlisten',
            'p2_cluster',
            'p3_disziplinen',
            'p3_datenbankmatrix',
            'p3_geografische_filter',
            'p3_graue_literatur',
            'p4_suchstrings',
            'p4_thesaurus_mapping',
            'p5_prisma_zahlen',
            'p5_screening_kriterien',
            'p5_treffer',
            'p5_tool_entscheidung',
            'p6_luckenanalyse',
            'p7_synthese_methode',
            'p7_muster_konsistenz',
            'p7_grade_einschaetzung',
            'p8_limitationen',
            'p8_reproduzierbarkeitspruefung',
            'p8_suchprotokoll',
        ];
    }

    /**
     * `projekte` is identified by its own `id`; all other tables carry `projekt_id`.
     */
    private function projektColumn(string $table): string
    {
        return $table === 'projekte' ? 'id' : 'projekt_id';
    }
};
