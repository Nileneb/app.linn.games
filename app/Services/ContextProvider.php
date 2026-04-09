<?php

namespace App\Services;

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Log;

/**
 * Builds the system message and messages array for the Chat-Agent.
 *
 * Replaces the former LangdockContextInjector for chat-agent use cases.
 * Provides semantic context only: project metadata, current phase, RAG chunks.
 *
 * Deliberately omits:
 * - DB schema snippets (no table names, column lists, or SQL)
 * - RLS bootstrap (no SET LOCAL app.current_projekt_id)
 */
class ContextProvider
{
    public function __construct(private readonly RetrieverService $retrieverService) {}

    /**
     * Baut die System-Message für den Chat-Agent.
     * Enthält: Projekt-Metadaten, aktuelle Phase-Info, RAG-Chunks.
     * KEIN DB-Schema, KEIN RLS-Bootstrap.
     *
     * @param  string  $projektId  UUID des Projekts
     * @param  string  $workspaceId  UUID des Workspaces (für RAG-Isolation)
     * @param  string  $userId  ID des Nutzers (für RAG-Isolation)
     * @param  string  $userQuery  Nutzeranfrage — wird für RAG-Retrieval verwendet
     * @param  int  $topK  Anzahl der RAG-Chunks (Standard: 10)
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException wenn Projekt nicht existiert
     */
    public function buildSystemMessage(
        string $projektId,
        string $workspaceId,
        string $userId,
        string $userQuery,
        int $topK = 10
    ): string {
        $projekt = Projekt::with('phasen')->findOrFail($projektId);

        $aktuellePhaseNr = $this->resolveAktuellePhase($projekt);

        $ragContext = $this->retrieveRagContext($userQuery, $projektId, $workspaceId, $userId, $topK);

        return $this->assembleSystemMessage($projekt, $aktuellePhaseNr, $ragContext);
    }

    /**
     * Baut das komplette messages-Array für den Agent-Call.
     * Format: [system-message, ...chat-history]
     *
     * @param  string  $userQuery  Aktuelle Nutzeranfrage (für RAG-Retrieval)
     * @param  array  $chatHistory  Bisherige Nachrichten: [['role' => '...', 'content' => '...']]
     * @return array<int, array{role: string, content: string}>
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException wenn Projekt nicht existiert
     */
    public function buildMessages(
        string $projektId,
        string $workspaceId,
        string $userId,
        string $userQuery,
        array $chatHistory = []
    ): array {
        $systemMessage = $this->buildSystemMessage(
            $projektId,
            $workspaceId,
            $userId,
            $userQuery
        );

        $systemEntry = [
            'role' => 'system',
            'content' => $systemMessage,
        ];

        return [$systemEntry, ...$chatHistory];
    }

    /**
     * Ermittelt die aktuelle Phase-Nummer aus den geladenen Phasen.
     *
     * Logik (absteigend priorisiert):
     * 1. Niedrigste Phase mit status = 'in_arbeit'  → laufende Arbeitsphase
     * 2. Falls keine 'in_arbeit'-Phase vorhanden: höchste phase_nr → Fortschritt des Projekts
     * 3. Falls keine Phasen vorhanden: null
     */
    private function resolveAktuellePhase(Projekt $projekt): ?int
    {
        $phasen = $projekt->phasen->sortBy('phase_nr');

        $inArbeit = $phasen->firstWhere('status', 'in_arbeit');
        if ($inArbeit !== null) {
            return (int) $inArbeit->phase_nr;
        }

        $letzte = $phasen->last();

        return $letzte !== null ? (int) $letzte->phase_nr : null;
    }

    /**
     * Ruft RAG-Chunks aus paper_embeddings + agent_result_embeddings ab.
     *
     * Fehlerbehandlung: Bei Fehler wird ein leerer String zurückgegeben —
     * die System-Message wird trotzdem gebaut (ohne Chunks).
     */
    private function retrieveRagContext(
        string $userQuery,
        string $projektId,
        string $workspaceId,
        string $userId,
        int $topK
    ): string {
        try {
            $chunks = $this->retrieverService->retrieveWithAgentResults(
                $userQuery,
                $projektId,
                $workspaceId,
                $userId,
                $topK
            );

            return $this->retrieverService->formatAsContext($chunks);
        } catch (\Throwable $e) {
            Log::warning('ContextProvider: RAG-Retrieval fehlgeschlagen, fahre ohne Chunks fort', [
                'projekt_id' => $projektId,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Montiert die fertige System-Message aus Projekt-Metadaten und RAG-Kontext.
     * Enthält kein DB-Schema und keine internen IDs.
     */
    private function assembleSystemMessage(
        Projekt $projekt,
        ?int $aktuellePhaseNr,
        string $ragContext
    ): string {
        $phaseLabel = $aktuellePhaseNr !== null ? "P{$aktuellePhaseNr}" : 'unbekannt';

        $lines = [
            'Du bist ein Assistent für systematische Literaturrecherche.',
            '',
            '## Aktuelles Projekt',
            '- Titel: '.($projekt->titel ?? '—'),
            '- Forschungsfrage: '.($projekt->forschungsfrage ?? '—'),
            '- Aktuelle Phase: '.$phaseLabel,
        ];

        if ($ragContext !== '') {
            $lines[] = '';
            $lines[] = '## Relevante Kontextinformationen (aus Phasen-Ergebnissen und Papers)';
            $lines[] = $ragContext;
        }

        $lines[] = '';
        $lines[] = '## Deine Aufgabe';
        $lines[] = 'Beantworte die Frage des Nutzers auf Basis des obigen Kontexts.';

        return implode("\n", $lines);
    }
}
