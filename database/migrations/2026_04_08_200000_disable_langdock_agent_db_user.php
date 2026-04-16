<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Datenbankbenutzer, der deaktiviert werden soll.
     * Wird aus der Umgebungsvariable gelesen; Fallback auf 'langdock_agent'.
     */
    private string $roleName;

    public function __construct()
    {
        $this->roleName = env('LANGDOCK_DB_USERNAME', 'langdock_agent');
    }

    /**
     * Deaktiviert den langdock_agent-Datenbankbenutzer.
     * Aktive Sessions werden beendet, danach wird das Login-Recht entzogen.
     */
    public function up(): void
    {
        // Nur für PostgreSQL relevant
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $role = $this->roleName;

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $role)) {
            Log::warning("Migration: Ungültiger Rollenname '{$role}' — übersprungen.");

            return;
        }

        if ($role === env('DB_USERNAME', 'linn_games')) {
            Log::warning("Migration: LANGDOCK_DB_USERNAME ist identisch mit DB_USERNAME ('{$role}') — Migration wird übersprungen um Datenbankzugriff nicht zu sperren.");

            return;
        }

        try {
            DB::statement(
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE usename = ? AND pid <> pg_backend_pid()',
                [$role]
            );

            // Login-Recht entziehen — keine neuen Verbindungen mehr möglich
            DB::statement("ALTER ROLE \"{$role}\" NOLOGIN");

            Log::info("Migration: Datenbankbenutzer '{$role}' wurde erfolgreich deaktiviert (NOLOGIN).");
        } catch (\Exception $e) {
            // Wenn die Role nicht existiert, Migration trotzdem als erfolgreich werten
            Log::info("Migration: Datenbankbenutzer '{$role}' nicht gefunden oder konnte nicht deaktiviert werden — wird übersprungen.", [
                'fehler' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Stellt das Login-Recht des langdock_agent-Benutzers wieder her (Rollback).
     */
    public function down(): void
    {
        // Nur für PostgreSQL relevant
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $role = $this->roleName;

        try {
            // Login-Recht wiederherstellen
            DB::statement("ALTER ROLE \"{$role}\" LOGIN");

            Log::info("Migration Rollback: Login-Recht für Datenbankbenutzer '{$role}' wurde wiederhergestellt.");
        } catch (\Exception $e) {
            // Wenn die Role nicht existiert, Rollback trotzdem als erfolgreich werten
            Log::info("Migration Rollback: Datenbankbenutzer '{$role}' nicht gefunden — wird übersprungen.", [
                'fehler' => $e->getMessage(),
            ]);
        }
    }
};
