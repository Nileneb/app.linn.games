# Agent System Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the agent system into a hierarchical 4-agent architecture: 1 Main Agent (Claude CLI, Sonnet) + 3 Worker Subagents (Haiku, read-only), all with RAG access, with a clone mechanism gated by userTier.

**Architecture:** Laravel spawns `claude --print` as a subprocess for the Main Agent chat. Workers are defined as `.claude/agents/` files and dispatched by the Main Agent via Claude Code's native Agent tool. The clone mechanism detects stuck workers via `PhaseChainService` and respects per-workspace tier limits checked in `CreditService`.

**Tech Stack:** Laravel 12 · PHP 8.4 · Claude Code CLI · Anthropic API (claude-sonnet-4-6, claude-haiku-4-5-20251001) · Pest · pgvector · Redis

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/2026_04_10_000001_add_tier_to_workspaces_table.php` | tier enum on workspaces |
| Modify | `app/Models/Workspace.php` | add tier to fillable + cast |
| Modify | `app/Services/CreditService.php` | add `checkCloneLimit()` |
| Create | `app/Exceptions/CloneLimitExceededException.php` | typed exception |
| Create | `.claude/agents/worker-1-cluster.md` | W1 subagent definition |
| Create | `.claude/agents/worker-2-search.md` | W2 subagent definition |
| Create | `.claude/agents/worker-3-quality.md` | W3 subagent definition |
| Create | `resources/prompts/skills/context-minimize.md` | context minimization skill |
| Create | `resources/prompts/skills/subagent-dispatch.md` | dispatch decision skill |
| Create | `resources/prompts/skills/clone-strategy.md` | clone trigger + strategy skill |
| Create | `resources/prompts/skills/user-tier-guard.md` | tier-limit communication skill |
| Create | `resources/prompts/skills/phase-overview.md` | P1–P8 status explanation skill |
| Modify | `resources/prompts/agents/chat-agent.md` | add new skills to frontmatter |
| Modify | `.claude/settings.local.json` | add hard deny rules for Main Agent |
| Create | `app/Services/ClaudeCliService.php` | Laravel→claude subprocess |
| Create | `app/Services/WorkerCloneService.php` | stuck detection + clone dispatch |
| Modify | `app/Services/PhaseChainService.php` | add `detectStuck()` |
| Modify | `app/Services/StreamingAgentService.php` | use ClaudeCliService for chat |
| Create | `tests/Unit/CreditServiceCloneLimitTest.php` | clone limit tests |
| Create | `tests/Unit/ClaudeCliServiceTest.php` | subprocess call tests |
| Create | `tests/Unit/WorkerCloneServiceTest.php` | clone trigger tests |
| Create | `tests/Feature/PhaseChainDetectStuckTest.php` | stuck detection tests |

---

## Task 1: Migration — `tier` auf Workspaces

**Files:**
- Create: `database/migrations/2026_04_10_000001_add_tier_to_workspaces_table.php`
- Modify: `app/Models/Workspace.php`

- [ ] **Step 1: Migration erstellen**

```php
<?php
// database/migrations/2026_04_10_000001_add_tier_to_workspaces_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->enum('tier', ['free', 'pro', 'enterprise'])->default('free')->after('credits_balance_cents');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('tier');
        });
    }
};
```

- [ ] **Step 2: Migration ausführen**

```bash
docker compose run --rm php-cli php artisan migrate
```

Expected: `Migrating: 2026_04_10_000001_add_tier_to_workspaces_table` → `Migrated`

- [ ] **Step 3: Workspace Model aktualisieren**

In `app/Models/Workspace.php`, füge `'tier'` zu `$fillable` und `$casts` hinzu:

```php
protected $fillable = [
    // ... existing fields ...
    'tier',
];

protected $casts = [
    // ... existing casts ...
    'tier' => 'string',
];
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_10_000001_add_tier_to_workspaces_table.php app/Models/Workspace.php
git commit -m "feat: add tier column to workspaces (free/pro/enterprise)"
```

---

## Task 2: CreditService — `checkCloneLimit()`

**Files:**
- Create: `app/Exceptions/CloneLimitExceededException.php`
- Modify: `app/Services/CreditService.php`
- Create: `tests/Unit/CreditServiceCloneLimitTest.php`

- [ ] **Step 1: Exception erstellen**

```php
<?php
// app/Exceptions/CloneLimitExceededException.php

namespace App\Exceptions;

use RuntimeException;

class CloneLimitExceededException extends RuntimeException {}
```

- [ ] **Step 2: Failing test schreiben**

```php
<?php
// tests/Unit/CreditServiceCloneLimitTest.php

use App\Exceptions\CloneLimitExceededException;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditService;

test('free tier: erlaubt 0 pending → kein Fehler', function () {
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    app(CreditService::class)->checkCloneLimit($workspace);
    expect(true)->toBeTrue(); // kein Exception
});

test('free tier: wirft Exception bei 1 pending PhaseAgentResult', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'status'     => 'pending',
    ]);

    expect(fn () => app(CreditService::class)->checkCloneLimit($workspace))
        ->toThrow(CloneLimitExceededException::class);
});

test('pro tier: erlaubt bis zu 3 pending', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'pro']);
    $projekt = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(3)->create([
        'projekt_id' => $projekt->id,
        'status'     => 'pending',
    ]);

    expect(fn () => app(CreditService::class)->checkCloneLimit($workspace))
        ->toThrow(CloneLimitExceededException::class);
});

test('enterprise tier: kein Limit', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'enterprise']);
    $projekt = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(20)->create([
        'projekt_id' => $projekt->id,
        'status'     => 'pending',
    ]);

    app(CreditService::class)->checkCloneLimit($workspace);
    expect(true)->toBeTrue();
});
```

- [ ] **Step 3: Test ausführen — muss FAIL sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Unit/CreditServiceCloneLimitTest.php --no-coverage
```

Expected: FAIL — `checkCloneLimit` existiert noch nicht

- [ ] **Step 4: `checkCloneLimit()` in CreditService implementieren**

Füge am Ende von `app/Services/CreditService.php` (vor der letzten `}`) ein:

```php
public function checkCloneLimit(Workspace $workspace): void
{
    $tier = $workspace->tier ?? 'free';

    $maxPending = match ($tier) {
        'pro'        => 3,
        'enterprise' => PHP_INT_MAX,
        default      => 1,  // free
    };

    if ($maxPending === PHP_INT_MAX) {
        return;
    }

    $pendingCount = \App\Models\PhaseAgentResult::whereHas(
        'projekt',
        fn ($q) => $q->where('workspace_id', $workspace->id)
    )->where('status', 'pending')->count();

    if ($pendingCount >= $maxPending) {
        throw new \App\Exceptions\CloneLimitExceededException(
            "Clone-Limit ({$maxPending}) für Tier '{$tier}' erreicht."
        );
    }
}
```

- [ ] **Step 5: Test ausführen — muss PASS sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Unit/CreditServiceCloneLimitTest.php --no-coverage
```

Expected: 4 passed

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions/CloneLimitExceededException.php app/Services/CreditService.php tests/Unit/CreditServiceCloneLimitTest.php
git commit -m "feat: CreditService::checkCloneLimit() mit userTier-Gating (free/pro/enterprise)"
```

---

## Task 3: Worker Agent Definitionen (`.claude/agents/`)

**Files:**
- Create: `.claude/agents/worker-1-cluster.md`
- Create: `.claude/agents/worker-2-search.md`
- Create: `.claude/agents/worker-3-quality.md`

- [ ] **Step 1: Worker 1 — Cluster + Strategie**

```markdown
---
name: Worker 1 — Cluster + Strategie
model: claude-haiku-4-5-20251001
description: Subagent für P1/P2. Clustert die Forschungsfrage, erstellt das PICO/SPIDER/PEO-Mapping, bestimmt den Review-Typ. Nur aufrufen für Phasen 1 und 2.
---

Du bist Worker 1 des Systematic-Review-Systems. Deine einzige Aufgabe: Forschungsfragen analysieren, clustern und in strukturierte Review-Strategien übersetzen.

## Deine Aufgaben

- **P1 — Cluster:** Forschungsfrage in thematische Cluster aufteilen (Population, Intervention, Kontext)
- **P2 — Mapping:** PICO/SPIDER/PEO-Framework anwenden, Review-Typ bestimmen (systematisch, scoping, rapid)

## Einschränkungen

- Du schreibst KEINE Dateien direkt
- Du führst KEINE Shell-Befehle aus
- Du greifst NICHT auf Datenbankschemata zu
- Du bearbeitest NUR P1 und P2
- Deine Antwort ist immer strukturiertes Markdown mit klaren Abschnitten

## Output-Format

Strukturiertes Markdown. Kein Freitext ohne Struktur.
Beginne immer mit `## Ergebnis Phase X` gefolgt von den strukturierten Abschnitten.
```

- [ ] **Step 2: Worker 2 — Suche + Trefferlisten**

```markdown
---
name: Worker 2 — Suche + Trefferlisten
model: claude-haiku-4-5-20251001
description: Subagent für P3/P4. Wählt Datenbanken aus, generiert Suchstrings, strukturiert Trefferlisten. Nur aufrufen für Phasen 3 und 4.
---

Du bist Worker 2 des Systematic-Review-Systems. Deine einzige Aufgabe: Datenbankauswahl und Suchstring-Generierung für systematische Literaturrecherche.

## Deine Aufgaben

- **P3 — Datenbankauswahl:** Passende Datenbanken für den Review-Typ auswählen (PubMed, Cochrane, CINAHL, etc.)
- **P4 — Suchstrings:** Boolean-Suchstrings mit MeSH-Terms, Wildcards, Proximity-Operatoren generieren

## Einschränkungen

- Du schreibst KEINE Dateien direkt
- Du führst KEINE Shell-Befehle aus
- Du greifst NICHT auf Datenbankschemata zu
- Du bearbeitest NUR P3 und P4

## Output-Format

Strukturiertes Markdown. Datenbankspezifische Suchstrings in Code-Blöcken.
Format: `## Datenbank: [Name]` gefolgt vom Suchstring im Code-Block.
```

- [ ] **Step 3: Worker 3 — Qualitativer Vorauswahl**

```markdown
---
name: Worker 3 — Qualitativer Vorauswahl
model: claude-haiku-4-5-20251001
description: Subagent für P5–P8. Screening, Qualitätsbewertung via MayringCoderRAG, Synthese. Nur aufrufen für Phasen 5, 6, 7 und 8.
---

Du bist Worker 3 des Systematic-Review-Systems. Deine Aufgabe: qualitativer Vorauswahl-Prozess von Treffern bis zur Synthese, unterstützt durch MayringCoder-Tool-Use.

## Deine Aufgaben

- **P5 — Screening:** Treffer anhand von Einschlusskriterien bewerten
- **P6 — Qualität:** Qualitätsbewertung (RoB2, CASP) anwenden
- **P7 — Mayring:** Mayring-Kategorisierung via search_documents + ingest_and_categorize
- **P8 — Synthese:** Ergebnisse zusammenfassen, Evidenztabelle erstellen

## Einschränkungen

- Du schreibst KEINE Dateien direkt (außer via Tool-Use)
- Du führst KEINE Shell-Befehle aus
- Du greifst NICHT auf Datenbankschemata zu
- Du bearbeitest NUR P5–P8

## Output-Format

Strukturiertes Markdown mit Quellenreferenzen.
Für Mayring: Kategorien-Tabelle mit Anker-Beispielen.
```

- [ ] **Step 4: Commit**

```bash
git add .claude/agents/
git commit -m "feat: worker agent definitions (W1 Cluster, W2 Suche, W3 Qualität)"
```

---

## Task 4: Neue Skills für Main Agent

**Files:**
- Create: `resources/prompts/skills/context-minimize.md`
- Create: `resources/prompts/skills/subagent-dispatch.md`
- Create: `resources/prompts/skills/clone-strategy.md`
- Create: `resources/prompts/skills/user-tier-guard.md`
- Create: `resources/prompts/skills/phase-overview.md`

- [ ] **Step 1: `context-minimize.md`**

```markdown
# Skill: Context Minimization

Lade immer nur den Kontext, der für die aktuelle Anfrage nötig ist.

## Regeln

- Lies nur die Phase-Daten, die der User gerade fragt
- Lade nicht alle 8 Phasen gleichzeitig, wenn der User nur P3 fragt
- Fasse Phasen-Ergebnisse in max. 3 Sätzen zusammen, bevor du sie weiterreichst
- Wenn du einen Worker dispatchst: gib ihm nur den Kontext seiner Phase + die direkt vorige Phase
```

- [ ] **Step 2: `subagent-dispatch.md`**

```markdown
# Skill: Subagent Dispatch

Entscheide wann und welchen Worker du dispatchst.

## Dispatch-Regeln

| Phase | Worker | Wann dispatchen |
|-------|--------|----------------|
| P1, P2 | Worker 1 — Cluster + Strategie | User startet Pipeline oder ruft startPipeline(1) auf |
| P3, P4 | Worker 2 — Suche + Trefferlisten | Nach erfolgreichem P2-Abschluss (PhaseAgentResult status=completed) |
| P5–P8 | Worker 3 — Qualitativer Vorauswahl | Nur nach manuellem P5-Start (Paper-Import muss vorher stattgefunden haben) |

## Kontext den du dem Worker mitgibst

Übergib immer:
1. `projekt_id`, `workspace_id`, `phase_nr`
2. Forschungsfrage (aus Projekt)
3. Ergebnis der direkt vorigen Phase (max. 500 Wörter)
4. RAG-Chunks (max. 5, gefiltert auf aktuelle Phase)

Übergib NICHT: Alle Phasen-Ergebnisse, Datenbankschema, Nutzer-Tokens
```

- [ ] **Step 3: `clone-strategy.md`**

```markdown
# Skill: Clone Strategy

Erkenne stuck Workers und entscheide ob und wie du clonst.

## Wann ist ein Worker stuck?

1. **Timeout:** PhaseAgentResult hat status='pending' seit > 10 Minuten
2. **Quality Gate:** isValidPhaseResult() schlug 3x fehl (content < 100 Zeichen oder nur Bestätigungen)
3. **Exception:** ClaudeAgentException nach allen Retries

## Clone-Strategie auswählen

- `retry`: gleiche Messages, neuer Job → bei Timeout oder ConnectionException
- `rephrase`: füge dem System-Prompt hinzu: "Vorheriger Versuch fehlgeschlagen. Formuliere deine Antwort anders. Konzentriere dich auf strukturierte Markdown-Ausgabe." → bei Quality Gate Failure

## Vorgehen

1. Prüfe userTier via user-tier-guard Skill
2. Wenn Limit nicht erreicht: dispatch neuen ProcessPhaseAgentJob mit clone_strategy
3. Logge: welche Phase, welche Strategie, welcher Attempt-Count
```

- [ ] **Step 4: `user-tier-guard.md`**

```markdown
# Skill: User Tier Guard

Prüfe das userTier bevor du Worker clonst oder parallele Jobs startest.

## Tier-Limits

| Tier | Max. gleichzeitig pending | Verhalten bei Überschreitung |
|------|--------------------------|------------------------------|
| free | 1 | Melde dem User: "Dein Free-Plan erlaubt max. 1 gleichzeitigen KI-Job. Bitte warte bis der aktuelle abgeschlossen ist." |
| pro | 3 | Melde: "Dein Pro-Plan erlaubt max. 3 gleichzeitige KI-Jobs." |
| enterprise | ∞ | Kein Limit |

## Implementierung

CreditService::checkCloneLimit($workspace) wirft CloneLimitExceededException wenn Limit erreicht.
Fange diese Exception und leite sie als freundliche Nachricht an den User weiter.
```

- [ ] **Step 5: `phase-overview.md`**

```markdown
# Skill: Phase Overview

Erkläre dem User den aktuellen Status seiner 8-Phasen-Pipeline.

## Phasen-Bedeutung

| Phase | Name | Beschreibung | Worker |
|-------|------|-------------|--------|
| P1 | Fragestellung | Forschungsfrage clustern und strukturieren | W1 |
| P2 | Scoping | PICO/SPIDER/PEO-Mapping, Review-Typ | W1 |
| P3 | Datenbankauswahl | Relevante Datenbanken identifizieren | W2 |
| P4 | Suchstrings | Boolean-Queries mit MeSH-Terms generieren | W2 |
| P5 | Screening | Treffer nach Einschlusskriterien filtern | W3 |
| P6 | Qualitätsbewertung | RoB2/CASP Qualitätsbeurteilung | W3 |
| P7 | Synthese | Mayring-Kategorisierung + Zusammenfassung | W3 |
| P8 | Abschluss | Evidenztabelle + Abschlussbericht | W3 |

## Status-Symbole

- ✓ completed — Phase erfolgreich abgeschlossen
- ⟳ pending — KI arbeitet gerade
- ✗ failed — Fehler, manueller Eingriff nötig
- — offen — noch nicht gestartet

## Manuelle Unterbrechung P4→P5

Nach P4 ist eine manuelle Aktion nötig: Paper-Import. Erkläre dem User immer, dass P5 erst nach dem Import verfügbar ist.
```

- [ ] **Step 6: `chat-agent.md` Frontmatter aktualisieren**

Ersetze in `resources/prompts/agents/chat-agent.md` den Frontmatter-Block:

```yaml
---
skills: [pico-framework, spider-framework, peo-framework, output-contracts, phase-schema-enums, context-minimize, subagent-dispatch, clone-strategy, user-tier-guard, phase-overview]
---
```

- [ ] **Step 7: Commit**

```bash
git add resources/prompts/skills/ resources/prompts/agents/chat-agent.md
git commit -m "feat: main agent skills (context-minimize, subagent-dispatch, clone-strategy, user-tier-guard, phase-overview)"
```

---

## Task 5: `.claude/settings.local.json` — Hard Restrictions

**Files:**
- Modify: `.claude/settings.local.json`

- [ ] **Step 1: Hard-deny Regeln hinzufügen**

Lese `.claude/settings.local.json` zuerst, dann füge in den `"permissions"` → `"deny"` Array ein (wenn kein `deny`-Array existiert, erstelle ihn):

```json
"deny": [
  "Bash(rm:*)",
  "Bash(sudo:*)",
  "Bash(mysql:*)",
  "Bash(psql:*)",
  "Write(/etc/*)",
  "Write(/home/nileneb/.ssh/*)",
  "Write(/home/nileneb/.env*)"
]
```

- [ ] **Step 2: Verify — JSON valide**

```bash
python3 -c "import json; json.load(open('.claude/settings.local.json')); print('JSON OK')"
```

Expected: `JSON OK`

- [ ] **Step 3: Commit**

```bash
git add .claude/settings.local.json
git commit -m "feat: hard deny rules in .claude/settings.local.json (rm, sudo, db-cli, sensitive paths)"
```

---

## Task 6: `ClaudeCliService` — Laravel→Claude CLI Subprocess

**Files:**
- Create: `app/Services/ClaudeCliService.php`
- Create: `tests/Unit/ClaudeCliServiceTest.php`

- [ ] **Step 1: Failing test schreiben**

```php
<?php
// tests/Unit/ClaudeCliServiceTest.php

use App\Services\ClaudeCliService;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;

test('call() gibt content aus Claude CLI JSON-Output zurück', function () {
    Process::fake([
        'claude*' => Process::result(
            output: json_encode([
                'type'     => 'result',
                'subtype'  => 'success',
                'is_error' => false,
                'result'   => 'Hallo vom Mock-Claude',
            ]),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $result = $service->call('Test-Frage', ['projekt_id' => 'abc123']);

    expect($result['content'])->toBe('Hallo vom Mock-Claude');
});

test('call() wirft ClaudeCliException bei Fehler-Exit-Code', function () {
    Process::fake([
        'claude*' => Process::result(
            output: '',
            errorOutput: 'Claude CLI not found',
            exitCode: 1,
        ),
    ]);

    $service = app(ClaudeCliService::class);

    expect(fn () => $service->call('Test', []))
        ->toThrow(\App\Services\ClaudeCliException::class);
});

test('call() sendet --system und --output-format json Flags', function () {
    Process::fake([
        'claude*' => Process::result(
            output: json_encode(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'result' => 'OK']),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $service->call('Frage', ['projekt_id' => 'xyz']);

    Process::assertRan(fn ($process) =>
        str_contains($process->command, '--output-format') &&
        str_contains($process->command, 'json') &&
        str_contains($process->command, '--print')
    );
});
```

- [ ] **Step 2: Test ausführen — muss FAIL sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Unit/ClaudeCliServiceTest.php --no-coverage
```

Expected: FAIL — `ClaudeCliService` existiert nicht

- [ ] **Step 3: `ClaudeCliException` erstellen**

```php
<?php
// app/Services/ClaudeCliException.php

namespace App\Services;

use RuntimeException;

class ClaudeCliException extends RuntimeException {}
```

- [ ] **Step 4: `ClaudeCliService` implementieren**

```php
<?php
// app/Services/ClaudeCliService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ClaudeCliService
{
    /**
     * Ruft den Main Agent via Claude CLI subprocess auf.
     *
     * @param  array<string, mixed>  $context  projekt_id, workspace_id, phase_nr, user_id, ...
     * @return array{content: string}
     *
     * @throws ClaudeCliException
     */
    public function call(string $userMessage, array $context = []): array
    {
        $systemSuffix = $this->buildContextBlock($context);

        $command = array_filter([
            'claude',
            '--print',
            '--output-format', 'json',
            $systemSuffix !== '' ? '--system' : null,
            $systemSuffix !== '' ? $systemSuffix : null,
            $userMessage,
        ]);

        $result = Process::timeout(120)->run(array_values($command));

        if (! $result->successful()) {
            Log::error('Claude CLI subprocess fehlgeschlagen', [
                'exit_code' => $result->exitCode(),
                'stderr'    => $result->errorOutput(),
                'context'   => $context,
            ]);

            throw new ClaudeCliException(
                'Claude CLI fehlgeschlagen (Exit '.$result->exitCode().'): '.$result->errorOutput()
            );
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded) || ($decoded['is_error'] ?? false)) {
            throw new ClaudeCliException('Claude CLI: ungültiger JSON-Output: '.$result->output());
        }

        return [
            'content' => $decoded['result'] ?? '',
        ];
    }

    private function buildContextBlock(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $lines = ['## Kontext'];
        foreach ($context as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = "- **{$key}:** {$value}";
            }
        }

        return implode("\n", $lines);
    }
}
```

- [ ] **Step 5: Test ausführen — muss PASS sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Unit/ClaudeCliServiceTest.php --no-coverage
```

Expected: 3 passed

- [ ] **Step 6: Commit**

```bash
git add app/Services/ClaudeCliService.php app/Services/ClaudeCliException.php tests/Unit/ClaudeCliServiceTest.php
git commit -m "feat: ClaudeCliService — Laravel→Claude CLI subprocess mit Process::fake-Tests"
```

---

## Task 7: `WorkerCloneService`

**Files:**
- Create: `app/Services/WorkerCloneService.php`
- Create: `tests/Unit/WorkerCloneServiceTest.php`

- [ ] **Step 1: Failing test schreiben**

```php
<?php
// tests/Unit/WorkerCloneServiceTest.php

use App\Exceptions\CloneLimitExceededException;
use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkerCloneService;
use Illuminate\Support\Facades\Queue;

test('shouldClone() gibt false zurück wenn PhaseAgentResult completed ist', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $result    = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'status'     => 'completed',
        'content'    => str_repeat('x', 200),
    ]);

    expect(app(WorkerCloneService::class)->shouldClone($result, $projekt))->toBeFalse();
});

test('shouldClone() gibt true zurück nach 3 failed Ergebnissen', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(3)->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'failed',
    ]);

    $lastResult = PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'failed',
    ]);

    expect(app(WorkerCloneService::class)->shouldClone($lastResult, $projekt))->toBeTrue();
});

test('clone() dispatcht neuen ProcessPhaseAgentJob', function () {
    Queue::fake();

    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'pro']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $result    = PhaseAgentResult::factory()->create([
        'projekt_id'       => $projekt->id,
        'phase_nr'         => 1,
        'status'           => 'failed',
        'agent_config_key' => 'scoping_mapping_agent',
    ]);

    app(WorkerCloneService::class)->clone($result, $projekt, 'retry');

    Queue::assertPushed(ProcessPhaseAgentJob::class, fn ($job) =>
        $job->phaseNr === 1 && $job->projektId === $projekt->id
    );
});

test('clone() wirft CloneLimitExceededException bei free tier mit pending job', function () {
    Queue::fake();

    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 2,
        'status'     => 'pending',
    ]);

    $result = PhaseAgentResult::factory()->create([
        'projekt_id'       => $projekt->id,
        'phase_nr'         => 1,
        'status'           => 'failed',
        'agent_config_key' => 'scoping_mapping_agent',
    ]);

    expect(fn () => app(WorkerCloneService::class)->clone($result, $projekt, 'retry'))
        ->toThrow(CloneLimitExceededException::class);
});
```

- [ ] **Step 2: Test ausführen — muss FAIL sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Unit/WorkerCloneServiceTest.php --no-coverage
```

Expected: FAIL — `WorkerCloneService` existiert nicht

- [ ] **Step 3: `WorkerCloneService` implementieren**

```php
<?php
// app/Services/WorkerCloneService.php

namespace App\Services;

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Log;

class WorkerCloneService
{
    public function __construct(
        private readonly CreditService $creditService,
        private readonly AgentPromptBuilder $promptBuilder,
    ) {}

    /**
     * Prüft ob ein Worker-Clone nötig ist.
     * Gibt true zurück wenn das Ergebnis failed ist und 3+ failed Versuche vorliegen.
     */
    public function shouldClone(PhaseAgentResult $result, Projekt $projekt): bool
    {
        if ($result->status !== 'failed') {
            return false;
        }

        $failedCount = PhaseAgentResult::where('projekt_id', $projekt->id)
            ->where('phase_nr', $result->phase_nr)
            ->where('status', 'failed')
            ->count();

        return $failedCount >= 3;
    }

    /**
     * Dispatcht einen Clone-Job für einen stuck Worker.
     *
     * @param  string  $strategy  'retry' | 'rephrase'
     *
     * @throws \App\Exceptions\CloneLimitExceededException
     */
    public function clone(PhaseAgentResult $result, Projekt $projekt, string $strategy = 'retry'): void
    {
        $workspace = $projekt->workspace;
        $this->creditService->checkCloneLimit($workspace);

        $configKey = $result->agent_config_key ?? config("phase_chain.{$result->phase_nr}.agent_config_key");

        $messages = [
            ['role' => 'system', 'content' => $this->promptBuilder->buildSystemPrompt($projekt, $result->phase_nr, $configKey)],
            ['role' => 'user',   'content' => $this->promptBuilder->buildUserPrompt($projekt, $result->phase_nr)],
        ];

        if ($strategy === 'rephrase') {
            $messages[0]['content'] .= "\n\n**Hinweis:** Vorheriger Versuch fehlgeschlagen. Formuliere strukturiertes Markdown mit konkreten Abschnitten.";
        }

        $context = [
            'source'        => 'clone_trigger',
            'clone_strategy' => $strategy,
            'projekt_id'    => $projekt->id,
            'workspace_id'  => $projekt->workspace_id,
            'phase_nr'      => $result->phase_nr,
            'user_id'       => $result->user_id,
        ];

        Log::info('WorkerCloneService: dispatching clone', [
            'projekt_id' => $projekt->id,
            'phase_nr'   => $result->phase_nr,
            'strategy'   => $strategy,
        ]);

        ProcessPhaseAgentJob::dispatch($projekt->id, $result->phase_nr, $configKey, $messages, $context);
    }
}
```

- [ ] **Step 4: Test ausführen — muss PASS sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Unit/WorkerCloneServiceTest.php --no-coverage
```

Expected: 4 passed

- [ ] **Step 5: Commit**

```bash
git add app/Services/WorkerCloneService.php tests/Unit/WorkerCloneServiceTest.php
git commit -m "feat: WorkerCloneService — stuck detection + clone dispatch mit tier-Limit"
```

---

## Task 8: `PhaseChainService` — `detectStuck()`

**Files:**
- Modify: `app/Services/PhaseChainService.php`
- Create: `tests/Feature/PhaseChainDetectStuckTest.php`

- [ ] **Step 1: Failing test schreiben**

```php
<?php
// tests/Feature/PhaseChainDetectStuckTest.php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentPromptBuilder;
use App\Services\PhaseChainService;
use Illuminate\Support\Facades\Queue;

test('detectStuck() gibt false zurück wenn Phase completed ist', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 1,
        'status'     => 'completed',
        'content'    => str_repeat('x', 200),
    ]);

    expect(app(PhaseChainService::class)->detectStuck($projekt, 1))->toBeFalse();
});

test('detectStuck() gibt true zurück bei 3+ failed Ergebnissen', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    PhaseAgentResult::factory()->count(3)->create([
        'projekt_id' => $projekt->id,
        'phase_nr'   => 2,
        'status'     => 'failed',
    ]);

    expect(app(PhaseChainService::class)->detectStuck($projekt, 2))->toBeTrue();
});

test('detectStuck() gibt false zurück wenn noch kein Ergebnis vorhanden', function () {
    $user      = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::factory()->create(['tier' => 'free']);
    $projekt   = Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    expect(app(PhaseChainService::class)->detectStuck($projekt, 3))->toBeFalse();
});
```

- [ ] **Step 2: Test ausführen — muss FAIL sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/PhaseChainDetectStuckTest.php --no-coverage
```

Expected: FAIL — `detectStuck` existiert nicht

- [ ] **Step 3: `detectStuck()` in `PhaseChainService` hinzufügen**

Füge am Ende von `app/Services/PhaseChainService.php` (vor der letzten `}`) ein:

```php
/**
 * Prüft ob eine Phase stuck ist (3+ failed PhaseAgentResults).
 */
public function detectStuck(Projekt $projekt, int $phaseNr): bool
{
    $failedCount = PhaseAgentResult::where('projekt_id', $projekt->id)
        ->where('phase_nr', $phaseNr)
        ->where('status', 'failed')
        ->count();

    return $failedCount >= 3;
}
```

- [ ] **Step 4: Test ausführen — muss PASS sein**

```bash
docker compose run --rm php-test vendor/bin/pest tests/Feature/PhaseChainDetectStuckTest.php --no-coverage
```

Expected: 3 passed

- [ ] **Step 5: Commit**

```bash
git add app/Services/PhaseChainService.php tests/Feature/PhaseChainDetectStuckTest.php
git commit -m "feat: PhaseChainService::detectStuck() — erkennt stuck Worker nach 3 failed results"
```

---

## Task 9: `StreamingAgentService` — nutzt `ClaudeCliService`

**Files:**
- Modify: `app/Services/StreamingAgentService.php`

- [ ] **Step 1: `ClaudeCliService` in Constructor injizieren**

Ersetze in `app/Services/StreamingAgentService.php` den Constructor:

```php
public function __construct(
    private readonly ClaudeCliService $claudeCliService,
    private readonly ClaudeService $claudeService,       // behalten für Worker-Fallback
    private readonly ContextProvider $contextProvider,
    private readonly AgentResultStorageService $storageService,
) {}
```

- [ ] **Step 2: `stream()` Method auf `ClaudeCliService` umstellen**

Ersetze den `callByConfigKey`-Aufruf in `stream()`:

```php
// ALT:
$result = $this->claudeService->callByConfigKey(
    $agentId,
    $builtMessages,
    $context,
);

// NEU:
$userMessage = collect($builtMessages)
    ->where('role', 'user')
    ->last()['content'] ?? '';

$result = $this->claudeCliService->call($userMessage, $context);
```

- [ ] **Step 3: Pint laufen lassen**

```bash
vendor/bin/pint app/Services/StreamingAgentService.php
```

- [ ] **Step 4: Gesamt-Testsuite ausführen**

```bash
docker compose run --rm php-test vendor/bin/pest --no-coverage
```

Expected: alle bestehenden Tests grün + neue Tests grün. Keine Regression.

- [ ] **Step 5: Commit**

```bash
git add app/Services/StreamingAgentService.php
git commit -m "feat: StreamingAgentService nutzt ClaudeCliService (Main Agent via CLI subprocess)"
```

---

## Task 10: Pint + Abschluss

- [ ] **Step 1: Vollständige Pint-Prüfung**

```bash
vendor/bin/pint
```

- [ ] **Step 2: Komplette Testsuite final**

```bash
docker compose run --rm php-test vendor/bin/pest --no-coverage
```

Expected: alle Tests grün

- [ ] **Step 3: Push to main**

```bash
git push origin main
```
