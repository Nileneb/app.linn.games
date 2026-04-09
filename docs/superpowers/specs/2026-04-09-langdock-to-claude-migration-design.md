# Spec: Langdock → Claude API Migration (Spec 1 von 2)

**Datum:** 2026-04-09  
**Status:** Approved  
**Scope:** Vollständige Ablösung von Langdock durch Anthropic Claude API. Kein Adapter, kein Feature-Flag — Big Bang (keine produktiven Nutzer, Pre-Beta).

---

## Kontext

Die App ist aktuell an Langdock als KI-Plattform gebunden. Langdock nimmt eine Marge auf Claude-Tokens, verwaltet Agent-Instruktionen extern (nicht versioniert), und ist ein Single-Point-of-Failure. Die Migration hat drei Ziele: Kostensenkung, vollständige Code-Kontrolle über Prompts, Plattformunabhängigkeit.

**Abgrenzung zu Spec 2 (Orchestrator):** Diese Spec bringt das System auf Claude API mit identischem Verhalten (Single-Agent pro Phase). Spec 2 fügt danach den Sonnet-Orchestrator + Haiku-Fleet + Tier-System hinzu.

---

## Kernprinzip

```
SendAgentMessage::execute()
  → ClaudeService::callByConfigKey()   [NEU — ersetzt LangdockAgentService]
      → PromptLoaderService            [NEU — lädt .md Prompts]
      → ClaudeContextBuilder           [NEU — ersetzt LangdockContextInjector]
      → Anthropic PHP SDK
      ← ['content' => string, 'raw' => array, 'tokens_used' => int]
  → AgentPayloadService::persistPayload()   [UNVERÄNDERT]
  → PhaseChainService::maybeDispatchNext()  [UNVERÄNDERT]
```

`SendAgentMessage` bekommt `ClaudeService` statt `LangdockAgentService` injiziert — identische Signatur, der Rest der Pipeline merkt nichts.

---

## Was bleibt unverändert

- `AgentPayloadService` — parst JSON Envelope v1, schreibt in DB
- `LangdockArtifactService` — speichert `.md`-Artefakte (Umbenennung optional)
- `CreditService` + `CreditTransaction` — nur Preis-Config anpassen
- `ProcessPhaseAgentJob` — ruft nur `SendAgentMessage::execute()` auf
- `PhaseChainService` — Auto-Chain identisch
- `AgentResultStorageService` — unverändert
- JSON Envelope v1 — Agenten geben weiterhin dasselbe Format zurück
- SSE-Format zum Browser — `data: {"text": "...", "raw": {...}}\n\n`

---

## Was wird ersetzt / entfernt

| Alt | Neu |
|-----|-----|
| `LangdockAgentService` | `ClaudeService` |
| `LangdockContextInjector` | `ClaudeContextBuilder` |
| `LangdockMcpClient` | Claude SDK Streaming in `ClaudeService` |
| `LangdockAgentException` | `ClaudeAgentException` |
| `LangdockConnectionException` | entfällt (in `ClaudeAgentException` zusammengeführt) |
| Agent-Instruktionen in Langdock-Plattform | `.md`-Dateien in `resources/prompts/agents/` |
| Langdock Skill-IDs in Instruktionen | Lokale Skills via YAML-Frontmatter |
| `AgentResultWebhookController` | **entfernt** (kein Webhook bei Claude API) |
| `listAgents()` | **entfernt** (Agenten leben im Code) |
| Langdock URLs in `config/services.php` | Anthropic-Config-Block |

---

## Service-Layer

### `ClaudeService`

```php
namespace App\Services;

class ClaudeService
{
    public function callByConfigKey(
        string $configKey,
        array $messages,
        array $context = [],
        int $maxTokens = 8192,
    ): array;
    // Returns: ['content' => string, 'raw' => array, 'tokens_used' => int]

    public function stream(
        string $configKey,
        array $messages,
        array $context = [],
    ): \Generator;
    // Yields SSE-Chunks für StreamingAgentService
}
```

Interner Ablauf bei `callByConfigKey`:
1. Config-Key → Prompt-Datei via `config('services.anthropic.agents')`
2. `PromptLoaderService::buildSystemPrompt($promptFile)` — lädt Agent + Skills
3. `ClaudeContextBuilder::build($context)` — lädt Projektdaten aus DB, gibt Markdown-Block
4. Anthropic SDK: `$client->messages()->create([system, messages])`
5. Token-Zählung: `$response->usage->inputTokens + $response->usage->outputTokens`
6. `CreditService::deduct()` mit separaten Input/Output-Tokens

Für den **Mayring-Agent** gilt ein erweiterter Ablauf mit Tool-Use-Loop (siehe Abschnitt MayringCoder).

### `PromptLoaderService`

Lädt `.md`-Dateien aus `resources/prompts/`, liest YAML-Frontmatter, konkateniert Skills.

```php
public function buildSystemPrompt(string $agentKey): string;
// 1. Lädt resources/prompts/agents/{agentKey}.md
// 2. Liest Frontmatter: skills: [pico-framework, searchterm-syntax, ...]
// 3. Hängt jede Skill-.md an
// 4. Gibt fertigen System-Prompt zurück
```

### `ClaudeContextBuilder`

Ersetzt `LangdockContextInjector` — kein `SET LOCAL` mehr, kein direkter DB-Zugriff vom Agent.

```php
public function build(array $context): string;
// Input: ['projekt_id', 'workspace_id', 'user_id', 'phase_nr', ...]
// Output: Markdown-Block der an System-Prompt angehängt wird
```

Geladene Daten (abhängig von `phase_nr`):
- Immer: `Projekt` (Forschungsfrage, Review-Typ, Titel)
- Phase ≥ 2: `p1Komponenten`, `p1Kriterien`
- Phase ≥ 3: `p2Cluster`
- Phase ≥ 4: `p3Datenbankmatrix`
- Phase ≥ 5: `p4Suchstrings`, Treffer-Count
- Phase ≥ 6: `p5ScreeningEntscheidungen` (nur eingeschlossen)
- Phase ≥ 7: `p6Qualitaetsbewertung`

Format-Beispiel:
```markdown
## Projektkontext
- Forschungsfrage: KI-Einsatz in der Altenpflege
- Review-Typ: scoping_review
- Aktuelle Phase: P5

## Vorgeladene Phasendaten (P1–P4)
### Komponenten (SPIDER-Framework)
| Kürzel | Label |
|--------|-------|
| S | Pflegekräfte in stationärer Altenpflege |
...

## Output-Anforderung
JSON Envelope v1 — exakt EIN gültiges JSON-Objekt...
```

---

## Prompt-Struktur

```
resources/prompts/
  agents/
    mapping-agent.md        P1–P3 Scoping & Mapping
    pico-agent.md           P1/P4 PICO fokussierte Suche
    screening-agent.md      P5 L1/L2 Screening
    quality-agent.md        P6 RoB / CASP / AMSTAR
    synthesis-agent.md      P7 Evidenzsynthese
    mayring-agent.md        P7 qualitative Codierung (mit Tool Use)
    chat-agent.md           Dashboard Chat
  skills/
    pico-framework.md       ← aus .github/skills/ bereinigt
    spider-framework.md
    peo-framework.md
    searchterm-syntax.md
    wellen-logik.md
    prisma-tracking.md
    phase-schema-enums.md
    output-contracts.md
```

YAML-Frontmatter Beispiel (`pico-agent.md`):
```yaml
---
skills: [pico-framework, searchterm-syntax, wellen-logik, prisma-tracking, output-contracts, phase-schema-enums]
---
# PICO Search Agent
...
```

**Prompt-Migration:** Die Langdock-Exports aus `docs/AgentsExport/` werden bereinigt:

| Entfernen | Ersatz |
|-----------|--------|
| `execute_sql` Tool-Referenzen | — (kein DB-Zugriff) |
| `SET LOCAL app.current_projekt_id` | `ClaudeContextBuilder` liefert Daten |
| Langdock Skill-IDs (`c1a3217d-...`) | YAML-Frontmatter + lokale Skills |
| `=== FLEET PATCH v1 ===` Marker | — |
| Wissensspeicher-Referenzen | Context-Block von `ClaudeContextBuilder` |

Business-Logik bleibt: Rollen-Definition, Workflow-Schritte, Wellen-Logik, PICO/RoB-Regeln, Output-Contract.

---

## MayringCoder-MCP Integration

Der Mayring-Agent erhält als einziger Agent Tool-Use-Zugriff auf den MayringCoder-Service (Docker, Auth-Header, eigenes Chroma + SQLite — kein PostgreSQL-Zugriff).

### `MayringMcpClient`

```php
namespace App\Services;

class MayringMcpClient
{
    public function searchDocuments(string $query, array $categories = [], int $topK = 8): array;
    public function ingestAndCategorize(string $content, string $sourceId): array;
    public function getChunk(string $chunkId): array;
    public function listBySource(string $sourceId): array;
}
// Calls config('services.mayring_mcp.endpoint') mit Bearer-Token
```

### Tool-Definitionen für Claude (Mayring-Agent)

```php
$tools = [
    [
        'name' => 'search_documents',
        'description' => 'Semantische Suche über Dokument-Chunks mit optionalem Mayring-Kategorie-Filter',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'query'      => ['type' => 'string'],
                'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'top_k'      => ['type' => 'integer', 'default' => 8],
            ],
            'required' => ['query'],
        ],
    ],
    [
        'name' => 'ingest_and_categorize',
        'description' => 'Inhalt ingesten und Mayring-Qualitätskategorisierung via Ollama ausführen',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'content'   => ['type' => 'string'],
                'source_id' => ['type' => 'string'],
            ],
            'required' => ['content', 'source_id'],
        ],
    ],
];
```

### Tool-Use-Loop in `ClaudeService`

```
Mayring-Agent Call:
  1. Initiale Anfrage mit $tools an Claude
  2. while ($response->stopReason === 'tool_use'):
       foreach ($response->toolUseBlocks as $toolCall):
         $result = $mayringMcpClient->{$toolCall->name}($toolCall->input)
       → tool_result zurück an Claude
  3. Finale Antwort (stop_reason = 'end_turn') → JSON Envelope v1
```

### Config

```php
'mayring_mcp' => [
    'endpoint'   => env('MAYRING_MCP_ENDPOINT', 'http://localhost:8090'),
    'auth_token' => env('MAYRING_MCP_AUTH_TOKEN'),
    'timeout'    => 60,
],
```

---

## Config (`config/services.php`)

```php
'anthropic' => [
    'api_key'    => env('CLAUDE_API_KEY'),
    'model'      => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
    'max_tokens' => 8192,
    'retry_attempts' => 3,
    'retry_sleep_ms' => 500,

    // Config-Key → Prompt-Datei Mapping
    'agents' => [
        'scoping_mapping_agent' => 'mapping-agent',
        'search_agent'          => 'pico-agent',
        'review_agent'          => 'screening-agent',
        'evaluation_agent'      => 'quality-agent',
        'synthesis_agent'       => 'synthesis-agent',
        'mayring_agent'         => 'mayring-agent',
        'agent_id'              => 'chat-agent',
    ],

    // Pricing (Haiku 4.5: $0.80/$4.00 per M tokens)
    'price_per_1k_input_tokens_cents'  => env('CLAUDE_INPUT_PRICE_CENTS', 1),
    'price_per_1k_output_tokens_cents' => env('CLAUDE_OUTPUT_PRICE_CENTS', 4),

    // Daily Limits (Cents, 0 = unbegrenzt)
    'agent_daily_limits' => [
        'scoping_mapping_agent' => 500,
        'search_agent'          => 500,
        'review_agent'          => 500,
        'evaluation_agent'      => 500,
        'synthesis_agent'       => 500,
        'mayring_agent'         => 1000,
        'agent_id'              => 500,
    ],
],
```

`CreditService::toCents()` wird auf Input/Output-Token-Splitting erweitert:
```php
public function toCents(int $inputTokens, int $outputTokens): int;
```

---

## Streaming

```
Bisher: LangdockMcpClient → eigener SSE-Parser → StreamingAgentService
Neu:    ClaudeService::stream() → Anthropic SDK stream() → StreamingAgentService
```

`StreamingAgentService` bekommt `ClaudeService` statt `LangdockMcpClient`. SSE-Format zum Browser bleibt identisch.

---

## Testing-Strategie

**`ClaudeServiceTest`** — mockt HTTP-Layer:
```php
Http::fake(['*api.anthropic.com*' => Http::response([
    'content' => [['type' => 'text', 'text' => '{"meta":...}']],
    'usage'   => ['input_tokens' => 100, 'output_tokens' => 500],
    'stop_reason' => 'end_turn',
])]);
```

**Integration-Tests** (SendAgentMessage, ProcessPhaseAgentJob) — mocken `ClaudeService` direkt:
```php
$this->mock(ClaudeService::class)
    ->shouldReceive('callByConfigKey')
    ->andReturn(['content' => '{"meta":{...}}', 'tokens_used' => 600]);
```

**`MayringMcpClientTest`** — mockt HTTP-Calls zum MayringCoder-Endpoint.

**`ClaudeContextBuilderTest`** — ersetzt `LangdockContextInjectorTest` (UUID-Validierung bleibt, SET LOCAL-Tests entfallen).

**`PromptLoaderServiceTest`** — prüft Frontmatter-Parsing, Skill-Konkatenation, fehlende Dateien.

---

## Zu entfernende Dateien

```
app/Services/LangdockAgentService.php
app/Services/LangdockContextInjector.php
app/Services/LangdockMcpClient.php
app/Services/LangdockAgentException.php
app/Services/LangdockConnectionException.php
app/Http/Controllers/AgentResultWebhookController.php
tests/Feature/Services/LangdockAgentServiceTest.php
tests/Feature/Services/LangdockContextInjectorTest.php
```

Route `POST /webhooks/langdock/agent-result` aus `routes/api.php` entfernen.

---

## Neue Dateien

```
app/Services/ClaudeService.php
app/Services/ClaudeContextBuilder.php
app/Services/ClaudeAgentException.php
app/Services/MayringMcpClient.php
app/Services/PromptLoaderService.php
resources/prompts/agents/mapping-agent.md
resources/prompts/agents/pico-agent.md
resources/prompts/agents/screening-agent.md
resources/prompts/agents/quality-agent.md
resources/prompts/agents/synthesis-agent.md
resources/prompts/agents/mayring-agent.md
resources/prompts/agents/chat-agent.md
resources/prompts/skills/pico-framework.md
resources/prompts/skills/spider-framework.md
resources/prompts/skills/peo-framework.md
resources/prompts/skills/searchterm-syntax.md
resources/prompts/skills/wellen-logik.md
resources/prompts/skills/prisma-tracking.md
resources/prompts/skills/phase-schema-enums.md
resources/prompts/skills/output-contracts.md
tests/Feature/Services/ClaudeServiceTest.php
tests/Feature/Services/ClaudeContextBuilderTest.php
tests/Feature/Services/MayringMcpClientTest.php
tests/Unit/PromptLoaderServiceTest.php
```

---

## Nicht in diesem Spec (→ Spec 2)

- Sonnet-Orchestrator
- Haiku-Fleet mit Parallelisierung
- Tier-System (max_concurrent_agents)
- Autonome Agent-Ketten ohne ProcessPhaseAgentJob

---

## Verification

```bash
# 1. Composer dependency
composer require anthropic-php/client

# 2. Alle Tests grün
docker compose run --rm php-test vendor/bin/pest

# 3. Linter
vendor/bin/pint --test

# 4. Smoke-Test: P1-Agent via Queue
php artisan queue:work --once
# → ProcessPhaseAgentJob läuft durch, PhaseAgentResult in DB gespeichert

# 5. Streaming-Test
curl -X POST /chat/stream -H "Authorization: Bearer ..." \
  -d '{"message": "Test"}' --no-buffer
# → SSE-Chunks fließen

# 6. Mayring Tool-Use
# → mayring_agent löst Tool-Call aus, MayringMcpClient antwortet,
#    finales JSON Envelope v1 wird persistiert
```
