# Roadmap — app.linn.games (SOLL-Zustand)

> Diese Datei beschreibt den **geplanten Zielzustand** der Architektur.
> Für den aktuellen IST-Zustand siehe `.claude/ARCHITECTURE.md`.
> Stand: April 2026.

---

## Ziel-Architektur: Unified Orchestration Layer

### Kernidee

Statt der aktuellen zwei Dispatch-Wege (TriggersPhaseAgent + ProcessPhaseAgentJob) soll ein **zentraler Orchestrator** alle Phasen-Aufrufe steuern. Das eliminiert Duplikate und stellt sicher, dass jeder Agent-Call dieselbe Pipeline durchläuft.

```
┌──────────────────────────────────────────────────────────────────┐
│                   ORCHESTRATION LAYER (PHP Service)              │
│                                                                  │
│  PhaseOrchestrator (vereint PhaseChainService + TriggersPhase)  │
│  ├─ canRunPhase()           (Dependency + Threshold Validation) │
│  ├─ buildPhaseContext()     (einzige Stelle für Kontext-Aufbau) │
│  ├─ dispatchPhase()         (immer via Queue)                   │
│  └─ handleCompletion()      (Quality Gate + Auto-Chain)         │
│                                                                  │
│  Location: app/Services/PhaseOrchestrator.php                   │
└──────────────────────────────────────────────────────────────────┘
```

---

## Geplante Änderungen

### 1. Kontext-Konsolidierung (Priorität: Hoch)

**Problem**: Kontext wird an 3 Stellen gebaut (AgentPromptBuilder, PhaseChainService::buildMessages, agent-action-button.blade.php).

**Lösung**: Ein zentraler `PhaseContextBuilder` ersetzt alle drei:

```php
// app/Services/PhaseContextBuilder.php
class PhaseContextBuilder
{
    public function build(Projekt $projekt, int $phaseNr, string $agentConfigKey): array
    {
        return [
            $this->projectMetadata($projekt),
            $this->previousPhaseResults($projekt, $phaseNr),
            $this->retrieverContext($projekt, $phaseNr),  // RAG-Chunks
            $this->rlsBootstrap($projekt),
            $this->phaseSchemaSnippet($phaseNr),
            $this->documentCounts($projekt),
        ];
    }
}
```

**Betroffene Issues**: #131 (Kontext-Aufbau), #111 (Agent-Flow)

### 2. Unified Dispatch (Priorität: Hoch)

**Problem**: Weg A (TriggersPhaseAgent) fehlt Retriever-Context, Synthesis-Enrichment und AgentPayloadService.

**Lösung**: `TriggersPhaseAgent` dispatcht künftig immer via `ProcessPhaseAgentJob`. Kein synchroner Agent-Call mehr aus Livewire.

```php
// Vorher (TriggersPhaseAgent):
$result = app(SendAgentMessage::class)->execute($configKey, $messages);
PhaseAgentResult::create([...]);

// Nachher:
ProcessPhaseAgentJob::dispatch($projekt->id, $phaseNr, $configKey, $messages, $context);
```

### 3. Structured Output als Standard (Priorität: Mittel)

**Problem**: Agents antworten mal Freitext, mal JSON-Envelope. Das macht Parsing unzuverlässig.

**Lösung**:
- Alle Phasen-Agents bekommen `structured_output=true` als Default
- Response-Schema wird pro Phase in `config/phase_chain.php` definiert:

```php
// config/phase_chain.php
1 => [
    'next_phase' => 2,
    'agent_config_key' => 'scoping_mapping_agent',
    'response_schema' => [
        'required_keys' => ['data'],
        'expected_tables' => ['p1_strukturmodell_wahl', 'p1_komponenten', 'p1_kriterien'],
    ],
],
```

- `ProcessPhaseAgentJob::parseStructuredResponse()` validiert gegen dieses Schema

**Betroffene Issues**: #119 (Structured Output), #122 (Quality Gate)

### 4. Quality Gate Erweiterung (Priorität: Mittel)

**Problem**: Quality Gate prüft nur Textlänge und Confirmation-Patterns.

**Geplante Checks**:
- Mindestanzahl DB-Writes pro Phase (z.B. P1 muss mindestens 1 Strukturmodell + 3 Komponenten schreiben)
- Schema-Validierung der JSON-Response
- Prüfung ob erwartete Tabellen befüllt wurden
- Optional: Semantic-Check via Embedding-Vergleich mit Forschungsfrage

### 5. P4→P5 Automatisierung (Priorität: Niedrig)

**Problem**: Nutzer muss manuell Papers importieren, bevor P5 starten kann.

**Geplant**:
- Nach P4-Abschluss: Automatisches Triggern der Paper-Suche via `mcp-paper-search`
- Notification an Nutzer: "X Papers gefunden, Import starten?"
- Bei ausreichend Papers: Auto-Dispatch von P5

### 6. Parallel Batch für P5 (Priorität: Niedrig)

**Problem**: P5-Screening verarbeitet alle Papers sequenziell.

**Geplant**:
- Papers in Batches aufteilen (z.B. 50 pro Batch)
- Parallele Queue-Jobs für Screening
- Merge-Schritt am Ende: PRISMA-Zahlen konsolidieren

---

## Feature-Backlog (nicht priorisiert)

| Feature | Beschreibung | Betroffene Issues |
|---------|-------------|-------------------|
| Human-in-the-Loop Gates | PI-Freigabe vor P5→P6 | — |
| Agent Performance Dashboard | Token-Verbrauch, Dauer, Erfolgsrate pro Phase | — |
| Phase Skip/Restart | Einzelne Phasen überspringen oder neu starten | — |
| WebSocket Live-Updates | Echtzeit-Fortschritt während Agent-Calls | #131 |
| Einladungslinks 24h + Cleanup | Registrierungs-Einladungen mit Ablauf | #150 |
| GitHub-Link in Sidebar | Quick-Link zum Repo | #151 |
| CI-Tests stabilisieren | Node 24, pgvector, ENV-Variablen | #152 |

---

## Migrations-Strategie

Die Umstellung auf den Unified Orchestrator erfolgt schrittweise:

1. **PhaseContextBuilder** extrahieren (rückwärtskompatibel)
2. **TriggersPhaseAgent** auf Queue-Dispatch umstellen
3. **PhaseChainService** in **PhaseOrchestrator** umbenennen und erweitern
4. **agent-action-button.blade.php** auf PhaseOrchestrator umstellen
5. **AgentPromptBuilder** und alte buildMessages()-Methoden entfernen

Jeder Schritt ist einzeln deploybar und testbar.
