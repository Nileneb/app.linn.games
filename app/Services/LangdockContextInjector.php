<?php

namespace App\Services;

/**
 * Injects context information into Langdock agent messages.
 * Handles PostgreSQL session variables and metadata for RLS/scoping.
 */
class LangdockContextInjector
{
    /**
     * Prepends a structured _context message so the agent knows which projekt/workspace/user to scope to.
     * Also serves as the source of truth for SET LOCAL app.current_projekt_id / app.current_workspace_id.
     *
     * @param  array<int, array{id: string, role: string, parts: array}>  $messages
     * @param  array{projekt_id?: string, workspace_id?: string, user_id?: string}  $context
     * @return array<int, array{id: string, role: string, parts: array}>
     */
    public function inject(array $messages, array $context): array
    {
        $projektId   = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId      = $context['user_id'] ?? null;

        if ($projektId === null && $workspaceId === null && $userId === null) {
            return $messages;
        }

        $lines = [];

        if ($projektId !== null) {
            $lines[] = 'WICHTIG: Setze als allererstes vor jeder Datenbankabfrage diese PostgreSQL-Session-Variable:';
            $lines[] = "SET LOCAL app.current_projekt_id = '{$projektId}';";
            $lines[] = '';
            $lines[] = 'Diese Variable MUSS bei jeder Verbindung gesetzt werden. Ohne sie greift Row Level Security '
                . 'und alle Abfragen liefern 0 Zeilen — Schreib-Operationen schlagen silent fehl.';
            $lines[] = '';
        } elseif ($workspaceId !== null) {
            $lines[] = 'WICHTIG: Setze als allererstes vor jeder Datenbankabfrage diese PostgreSQL-Session-Variable:';
            $lines[] = "SET LOCAL app.current_workspace_id = '{$workspaceId}';";
            $lines[] = '';
            $lines[] = 'Diese Variable MUSS bei jeder Verbindung gesetzt werden. Sie steuert, welche Workspace-Daten du sieht.';
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
