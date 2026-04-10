# Agent System Redesign — Design Spec

> Stand: 2026-04-10 | Status: Approved

---

## Ziel

Das bisherige Agent-System (Langdock → Claude API direkt) wird zu einer **hierarchischen 4-Agenten-Architektur** umgebaut:

- **1 Main Agent** (Orchestrator + Chat, Claude CLI, Sonnet 4.6)
- **3 Worker-Subagents** (Haiku 4.5, read-only, dispatched via `.claude/agents/`)
- **Alle Agents** haben RAG-Zugriff via `RetrieverService`
- **Clone-Mechanismus** bei stuck Workers, begrenzt durch userTier

---

## Architektur

### Kommunikationsflow

```
Browser (SSE)
  ↕
Laravel (ChatStreamController / ProcessPhaseAgentJob)
  → Process::run('claude --print --output-format json ...')
  ↕
Main Agent (claude-sonnet-4-6 · Claude Code CLI)
  → .claude/agents/worker-1-cluster.md     (W1 · Haiku)
  → .claude/agents/worker-2-search.md      (W2 · Haiku)
  → .claude/agents/worker-3-quality.md     (W3 · Haiku)
  ↕
RAG-Bus (pgvector dual-source · Redis · Ollama)
```

### Laravel → Claude CLI

Laravel kommuniziert mit dem Main Agent via **subprocess**:

```php
$result = Process::run([
    'claude', '--print',
    '--output-format', 'json',
    '--system', $systemPrompt,
    $userMessage,
]);
```

- Stateless pro Request (kein persistenter Prozess)
- Output: JSON mit `content`, `tokens_used`
- Streaming: Fake-Chunks aus vollem Response (echtes `--output-format stream-json` als späteres Upgrade)

---

## Main Agent

### Identität

- **Modell:** `claude-sonnet-4-6`
- **CLI-Installation:** lokal im Dev-Env
- **Prompt-Datei:** `resources/prompts/agents/chat-agent.md`
- **Skills (YAML-Frontmatter):**

```yaml
---
skills:
  - context-minimize
  - rag-retrieval
  - subagent-dispatch
  - clone-strategy
  - phase-overview
  - user-tier-guard
---
```

### Fähigkeiten

| Fähigkeit | Implementierung |
|-----------|----------------|
| User-Chat | SSE via `StreamingAgentService` → subprocess |
| Phase-Überblick | Skill `phase-overview` erklärt P1–P8 Status |
| Worker dispatchen | Claude Agent-Tool → `.claude/agents/worker-*.md` |
| Worker clonen | Skill `clone-strategy` + `CreditService::checkCloneLimit()` |
| RAG abfragen | Skill `rag-retrieval` → `RetrieverService` via MCP-Tool |
| Context minimieren | Skill `context-minimize` — nur relevante Phasen laden |

### Hard Restrictions (`.claude/settings.json`)

```json
{
  "permissions": {
    "deny": [
      "Bash(*)",
      "Write(/home/*)",
      "Edit(/home/*)"
    ],
    "allow": [
      "Write(/home/nileneb/Desktop/WebDev/app.linn.games/storage/app/agent-results/*)"
    ]
  }
}
```

---

## Worker 1 — Cluster + Strategie

| Eigenschaft | Wert |
|-------------|------|
| Datei | `.claude/agents/worker-1-cluster.md` |
| Modell | `claude-haiku-4-5-20251001` |
| Config-Key | `scoping_mapping_agent` |
| Prompt-Datei | `resources/prompts/agents/mapping-agent.md` |
| Phasen | P1, P2 |

**Aufgabe:** Forschungsfrage clustern, PICO-Mapping, Review-Typ bestimmen.

**Erlaubte Tools:**
- RAG-Query (RetrieverService via HTTP-Tool)
- `PhaseAgentResult` schreiben (via definierten PHP-Endpoint)

**Verboten:** Bash, Dateisystem-Write, DB-Schema-Zugriff.

---

## Worker 2 — Suche + Trefferlisten

| Eigenschaft | Wert |
|-------------|------|
| Datei | `.claude/agents/worker-2-search.md` |
| Modell | `claude-haiku-4-5-20251001` |
| Config-Key | `search_agent` |
| Prompt-Datei | `resources/prompts/agents/pico-agent.md` |
| Phasen | P3, P4 |

**Aufgabe:** Datenbanken auswählen, Suchstrings generieren, Trefferlisten strukturieren.

**Erlaubte Tools:**
- RAG-Query
- SearchString + TrefferlisterEintrag schreiben (via Endpoint)

**Verboten:** Bash, Dateisystem-Write, DB-Schema-Zugriff.

---

## Worker 3 — Qualitativer Vorauswahl + MayringCoderRAG

| Eigenschaft | Wert |
|-------------|------|
| Datei | `.claude/agents/worker-3-quality.md` |
| Modell | `claude-haiku-4-5-20251001` |
| Config-Key | `mayring_agent` (Tool-Use) + `review_agent` / `evaluation_agent` / `synthesis_agent` |
| Prompt-Datei | `resources/prompts/agents/mayring-agent.md` (primär) |
| Phasen | P5, P6, P7, P8 |

**Aufgabe:** Screening, Qualitätsbewertung, MayringCoder-Kategorisierung, Synthese.

**Erlaubte Tools:**
- RAG-Query
- `MayringMcpClient::searchDocuments()` (Tool-Use via Claude API)
- `MayringMcpClient::ingestAndCategorize()` (Tool-Use)
- PhaseAgentResult schreiben

**Verboten:** Bash, Dateisystem-Write, DB-Schema-Zugriff.

**Besonderheit:** W3 nutzt Claude Tool-Use Loop (max 10 Iterationen) via `ClaudeService::callWithToolUse()`. Tool-Use läuft direkt über `ClaudeService`, nicht über CLI-Subprocess.

---

## Clone-Mechanismus

### Trigger

Ein Worker gilt als stuck wenn:
1. **Timeout:** `ProcessPhaseAgentJob` läuft > 10 Minuten ohne `PhaseAgentResult`
2. **Quality Gate:** `isValidPhaseResult()` schlägt 3x hintereinander fehl
3. **Exception:** `ClaudeAgentException` nach allen Retry-Versuchen

### Ablauf

```
PhaseChainService::detectStuck(phaseNr, projektId)
  → CreditService::checkCloneLimit(workspace, userTier)
      - Free: max 1 aktiver Clone
      - Pro: max 3 aktive Clones
      - Enterprise: unbegrenzt
  → ProcessPhaseAgentJob::dispatch(..., cloneStrategy)
      - 'retry':    gleiche Messages, neuer Job
      - 'rephrase': ClaudeContextBuilder fügt Rephrase-Hint in System-Prompt ein
```

### userTier-Felder

`Workspace` erhält neues Feld `tier` (enum: `free`, `pro`, `enterprise`). Default: `free`.

---

## Skills-System

Skills liegen unter `resources/prompts/skills/{skill-name}.md` und werden via YAML-Frontmatter in Agent-Prompts eingebunden.

### Neue Skills für Main Agent

| Skill | Datei | Inhalt |
|-------|-------|--------|
| `context-minimize` | `context-minimize.md` | Anweisung: nur relevante Phasen laden, nicht ganzen Projekt-Verlauf |
| `subagent-dispatch` | `subagent-dispatch.md` | Wann/wie Worker dispatchen, welcher Worker für welche Phase |
| `clone-strategy` | `clone-strategy.md` | Wann clonen, welche Strategie (retry vs. rephrase) |
| `user-tier-guard` | `user-tier-guard.md` | userTier prüfen vor Clone, Limit kommunizieren |
| `phase-overview` | `phase-overview.md` | P1–P8 Übersicht, Status erklären |
| `rag-retrieval` | `rag-retrieval.md` (existiert) | RAG-Query-Anweisung |

---

## Kostenkontrolle

| Maßnahme | Detail |
|----------|--------|
| Workers = Haiku | `model: claude-haiku-4-5-20251001` in allen Worker-`.md`-Dateien |
| Main Agent = Sonnet | Nur Orchestrierung, nicht Forschungsarbeit |
| Context-Cap | Skills selektiv geladen (YAML-Frontmatter pro Agent) |
| Clone-Limit | `CreditService::checkCloneLimit()` per userTier |
| Geschätzte Kosten | ~$0.13 pro vollständigem 8-Phasen-Run |

---

## Nicht in diesem Scope

- Echtes CLI-Streaming (`--output-format stream-json`) — späteres Upgrade
- Production-Deployment des CLI — nur Dev-Env
- `LangdockArtifactService` → `AgentArtifactService` Umbenennung — separater Task
- Admin-Panel Tests — separater Task

---

## Betroffene Dateien

| Aktion | Datei |
|--------|-------|
| Neu | `.claude/agents/worker-1-cluster.md` |
| Neu | `.claude/agents/worker-2-search.md` |
| Neu | `.claude/agents/worker-3-quality.md` |
| Neu | `resources/prompts/skills/context-minimize.md` |
| Neu | `resources/prompts/skills/subagent-dispatch.md` |
| Neu | `resources/prompts/skills/clone-strategy.md` |
| Neu | `resources/prompts/skills/user-tier-guard.md` |
| Neu | `resources/prompts/skills/phase-overview.md` |
| Neu | `app/Services/ClaudeCliService.php` (Laravel→CLI subprocess) |
| Neu | `app/Services/WorkerCloneService.php` |
| Modify | `app/Services/StreamingAgentService.php` → nutzt `ClaudeCliService` |
| Modify | `app/Services/CreditService.php` → `checkCloneLimit()` |
| Modify | `app/Services/PhaseChainService.php` → `detectStuck()` |
| Modify | `resources/prompts/agents/chat-agent.md` → neue Skills in Frontmatter |
| Modify | `.claude/settings.json` → Hard Restrictions für Main Agent |
| Modify | `database/migrations/` → `tier`-Spalte auf `workspaces` |
