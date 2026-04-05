<?php

namespace App\Services;

/**
 * Injects context information into Langdock agent messages.
 * Handles PostgreSQL session variables and metadata for RLS/scoping.
 */
class LangdockContextInjector
{
    /**
     * Validates if a value is either a valid UUID or a numeric ID.
     * Numeric IDs are used for user_id (User model uses auto-increment integers).
     *
     * @param  mixed  $value
     * @return bool
     */
    private function isValidIdentifier($value): bool
    {
        if ($value === null) {
            return true;
        }

        $stringValue = (string) $value;

        // Check if it's a numeric ID (integer like 1, 2, 123)
        if (ctype_digit($stringValue)) {
            return true;
        }

        // Otherwise check if it's a valid UUID format
        return $this->isValidUuid($stringValue);
    }

    /**
     * Validates if a string is a valid UUID format.
     *
     * @param  string|null  $value
     * @return bool
     */
    private function isValidUuid(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        // UUID format: 8-4-4-4-12 hex digits with hyphens
        if (strlen($value) !== 36) {
            return false;
        }

        if ($value[8] !== '-' || $value[13] !== '-' || $value[18] !== '-' || $value[23] !== '-') {
            return false;
        }

        // Check all characters except hyphens are valid hex
        $hexParts = [
            substr($value, 0, 8),      // 8 hex
            substr($value, 9, 4),      // 4 hex
            substr($value, 14, 4),     // 4 hex
            substr($value, 19, 4),     // 4 hex
            substr($value, 24, 12),    // 12 hex
        ];

        foreach ($hexParts as $part) {
            if (!ctype_xdigit($part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepends a structured _context message so the agent knows which projekt/workspace/user to scope to.
     * Also serves as the source of truth for SET LOCAL app.current_projekt_id / app.current_workspace_id.
     *
     * @param  array<int, array{id: string, role: string, parts: array}>  $messages
     * @param  array{projekt_id?: string, workspace_id?: string, user_id?: string}  $context
     * @return array<int, array{id: string, role: string, parts: array}>
     * @throws \InvalidArgumentException if UUIDs are invalid format
     */
    public function inject(array $messages, array $context): array
    {
        $projektId   = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId      = $context['user_id'] ?? null;

        // Validate UUIDs defensively before using in SQL context
        if ($projektId !== null && !$this->isValidUuid((string) $projektId)) {
            throw new \InvalidArgumentException("Invalid projekt_id format: '{$projektId}'. Must be a valid UUID.");
        }

        if ($workspaceId !== null && !$this->isValidUuid((string) $workspaceId)) {
            throw new \InvalidArgumentException("Invalid workspace_id format: '{$workspaceId}'. Must be a valid UUID.");
        }

        // user_id can be either a numeric ID (from User model) or UUID
        if ($userId !== null && !$this->isValidIdentifier($userId)) {
            throw new \InvalidArgumentException("Invalid user_id format: '{$userId}'. Must be a valid UUID or numeric ID.");
        }

        if ($projektId === null && $workspaceId === null && $userId === null) {
            return $messages;
        }

        $lines = [];

        if ($projektId !== null) {
            $lines[] = '=== DATENBANK-BOOTSTRAP (PFLICHT) ===';
            $lines[] = '';
            $lines[] = 'Du hast vollen Lese- UND Schreibzugriff (SELECT, INSERT, UPDATE, DELETE) auf die Projekt-Datenbank.';
            $lines[] = 'Dein Tool dafür ist `execute_sql` (postgres-dev Integration).';
            $lines[] = '';
            $lines[] = 'WICHTIG — Row Level Security ist aktiv. Damit deine Abfragen Daten sehen und schreiben können,';
            $lines[] = 'MUSS jeder `execute_sql`-Aufruf mit diesem SET LOCAL beginnen:';
            $lines[] = '';
            $lines[] = "SET LOCAL app.current_projekt_id = '{$projektId}';";
            $lines[] = '';
            $lines[] = 'Da jeder `execute_sql`-Aufruf eine eigene Transaktion sein kann, setze SET LOCAL IMMER als';
            $lines[] = 'erste Anweisung im selben SQL-Block — vor jedem SELECT, INSERT, UPDATE oder DELETE.';
            $lines[] = '';
            $lines[] = 'Beispiel für eine Abfrage:';
            $lines[] = "  execute_sql(\"SET LOCAL app.current_projekt_id = '{$projektId}'; SELECT * FROM projekte;\")";
            $lines[] = '';
            $lines[] = 'Beispiel für einen Insert:';
            $lines[] = "  execute_sql(\"SET LOCAL app.current_projekt_id = '{$projektId}'; INSERT INTO p1_fragestellung (id, projekt_id, ...) VALUES (gen_random_uuid(), '{$projektId}', ...);\")";
            $lines[] = '';
            $lines[] = 'Ohne SET LOCAL liefern alle Abfragen 0 Zeilen und Schreibvorgänge schlagen still fehl.';
            $lines[] = 'Dies IST dein DB-Bootstrap-Mechanismus — es gibt keinen anderen.';
            $lines[] = '';
        } elseif ($workspaceId !== null) {
            $lines[] = '=== DATENBANK-BOOTSTRAP (PFLICHT) ===';
            $lines[] = '';
            $lines[] = 'Du hast vollen Lese- UND Schreibzugriff auf die Workspace-Datenbank via `execute_sql` (postgres-dev).';
            $lines[] = '';
            $lines[] = 'WICHTIG — Row Level Security ist aktiv. Jeder `execute_sql`-Aufruf MUSS mit diesem SET LOCAL beginnen:';
            $lines[] = '';
            $lines[] = "SET LOCAL app.current_workspace_id = '{$workspaceId}';";
            $lines[] = '';
            $lines[] = 'Da jeder Aufruf eine eigene Transaktion sein kann, setze SET LOCAL IMMER als erste Anweisung im selben SQL-Block.';
            $lines[] = '';
        }

        $lines[] = 'Kontext: ' . json_encode(
            array_filter([
                'projekt_id' => $projektId,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
            ]),
            JSON_UNESCAPED_UNICODE,
        );

        $contextMessage = [
            'id'    => 'system_context',
            'role'  => 'system',
            'parts' => [['type' => 'text', 'text' => implode("\n", $lines)]],
        ];

        return [$contextMessage, ...$messages];
    }
}
