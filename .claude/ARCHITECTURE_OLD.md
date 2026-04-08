# Research Workflow Agent Architecture

## Executive Summary

**Goal:** Orchestrate 8 systematic review phases (P1-P8) with three specialized Langdock agents, using a **hybrid approach**: central PHP orchestration layer + specialized JSON-speaking agents (no MD-file communication, no single meta-agent).

**Architecture:**
```
Orchestration Layer (PHP)
  ↓
Three Specialized Langdock Agents
  ↓
Structured JSON I/O
  ↓
Atomic DB Transactions
```

---

## The Problem With Alternatives

### ❌ Option 1: One Meta-Agent with Subagents

```
MetaAgent
├── ScopingSubagent (P1-P3)
├── SearchSubagent (P4)
└── ReviewSubagent (P5-P8)
```

**Issues:**
- MetaAgent becomes massive & unmaintainable
- Must track all 8 phases + dependencies simultaneously
- Langdock provides **three separate specialized agents** with own configs — combining them wastes their specialization
- Single point of failure (if one subagent breaks, whole workflow breaks)
- No parallelization if future phases can run concurrently

### ❌ Option 2: Decentralized Agents + MD-File Communication

```
Phase1Agent → phase_1_result.md
              ├─ triggers P2Agent
              └─ P2Agent reads MD, writes phase_2_result.md
```

**Issues:**
- Too loose for mission-critical research workflows
- File I/O overhead (every phase reads/writes disk)
- Trigger words via regex are fragile & error-prone
- No built-in error handling or retry logic
- Git-based communication (like Claude-Code agents) is overkill for systematic reviews
- Difficult to validate schema compliance

---

## ✅ Hybrid Solution: Orchestration Layer + Specialized Agents

### Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│                   ORCHESTRATION LAYER (PHP Service)                  │
│                                                                       │
│  ResearchWorkflow (State Machine)                                    │
│  ├─ phaseRegistry[]          (P1-P8 metadata, preconditions)        │
│  ├─ canRunPhase()            (dependency validation)                │
│  ├─ buildPhaseContext()      (unified context pipeline)            │
│  ├─ dispatchPhase()          (send to agent)                       │
│  └─ handleAgentCompletion()  (auto-chain + quality gates)          │
│                                                                       │
│  Location: app/Services/ResearchWorkflow.php                        │
└──────────────────────────────────────────────────────────────────────┘
                               ↓
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
   ┌────────────┐        ┌────────────┐      ┌──────────────┐
   │ P1-P3:     │        │ P4:        │      │ P5-P8:       │
   │ scoping_   │        │ search_    │      │ review_      │
   │ mapping_   │        │ agent      │      │ agent        │
   │ agent      │        │            │      │              │
   └────────────┘        └────────────┘      └──────────────┘
        ↓                     ↓                     ↓
   Langdock API (EU GDPR-compliant proxy)
```

### Phase Registry

```php
// app/Services/ResearchWorkflow.php

private array $phaseRegistry = [
  1 => [
    'name'            => 'Strukturierung',
    'agent'           => 'scoping_mapping_agent',
    'preconditions'   => [],  // P1 can always run
    'output_tables'   => ['p1_strukturmodell_wahl', 'p1_komponenten', ...],
    'context_char_limit' => 1000,
  ],
  
  2 => [
    'name'            => 'Review-Typ',
    'agent'           => 'scoping_mapping_agent',
    'preconditions'   => [1],  // P2 requires P1 complete
    'output_tables'   => ['p2_review_typ_entscheidung', 'p2_cluster', ...],
    'context_char_limit' => 1000,
  ],
  
  3 => [
    'name'            => 'Quellen',
    'agent'           => 'scoping_mapping_agent',
    'preconditions'   => [1, 2],
    'output_tables'   => ['p3_datenbankmatrix', 'p3_disziplinen', ...],
    'context_char_limit' => 1000,
  ],
  
  4 => [
    'name'            => 'Suchstrings',
    'agent'           => 'search_agent',
    'preconditions'   => [1, 2, 3],
    'output_tables'   => ['p4_suchstrings', 'p4_thesaurus_mapping', ...],
    'context_char_limit' => 1500,
  ],
  
  5 => [
    'name'            => 'Screening',
    'agent'           => 'review_agent',
    'preconditions'   => [4, 'papers_imported'],  // P5 needs papers!
    'output_tables'   => ['p5_screening_entscheidung', 'p5_prisma_zahlen', ...],
    'context_char_limit' => 2000,
  ],
  
  6 => [
    'name'            => 'Qualität',
    'agent'           => 'review_agent',
    'preconditions'   => [5],
    'output_tables'   => ['p6_qualitaetsbewertung', 'p6_luckenanalyse', ...],
    'context_char_limit' => 2000,
  ],
  
  7 => [
    'name'            => 'Synthese',
    'agent'           => 'review_agent',
    'preconditions'   => [6],
    'output_tables'   => ['p7_datenextraktion', 'p7_synthese_methode', ...],
    'context_char_limit' => 2500,
  ],
  
  8 => [
    'name'            => 'Dokumentation',
    'agent'           => 'review_agent',
    'preconditions'   => [7],
    'output_tables'   => ['p8_suchprotokoll', 'p8_limitation', ...],
    'context_char_limit' => 3000,
    'always_write_artifact' => true,  // Forces markdown export
  ],
];
```

---

## Core Methods

### 1. Dependency Resolution: `canRunPhase()`

```php
public function canRunPhase(int $phaseNr, Projekt $projekt): bool {
  $preconditions = $this->phaseRegistry[$phaseNr]['preconditions'];
  
  foreach ($preconditions as $precond) {
    
    // Numeric precondition: previous phase must be complete
    if (is_int($precond)) {
      if (!$this->isPhaseComplete($precond, $projekt)) {
        return false;
      }
    }
    
    // String precondition: custom check (e.g., 'papers_imported')
    if (is_string($precond)) {
      if ($precond === 'papers_imported') {
        $paperCount = DB::selectOne(
          'SELECT COUNT(*) as cnt FROM paper_embeddings WHERE projekt_id = ?',
          [$projekt->id]
        )?->cnt ?? 0;
        
        if ($paperCount === 0) {
          return false;  // ⚠️ P4→P5 gate: blocks auto-dispatch if no papers
        }
      }
    }
  }
  
  return true;
}
```

**Usage:**
```php
// Check before dispatching P5
if ($this->canRunPhase(5, $projekt)) {
  $this->dispatchPhase(5, $projekt);
} else {
  // Send notification: "Import papers before proceeding to P5"
  Notification::send($projekt->user, new PaperImportRequired(5));
}
```

---

### 2. Unified Context Building: `buildPhaseContext()`

```php
public function buildPhaseContext(int $phaseNr, Projekt $projekt): array {
  
  // 1. Project metadata
  $projectContext = [
    'projekt_id'       => $projekt->id,
    'forschungsfrage'  => $projekt->forschungsfrage,
    'review_typ'       => $projekt->review_typ,
    'verantwortlich'   => $projekt->verantwortlich,
  ];
  
  // 2. Previous phase results (truncated)
  $contextLimit = $this->phaseRegistry[$phaseNr]['context_char_limit'];
  $previousResults = [];
  
  foreach (range(1, $phaseNr - 1) as $priorPhase) {
    $result = PhaseAgentResult::where('projekt_id', $projekt->id)
      ->where('phase_nr', $priorPhase)
      ->where('status', 'completed')
      ->latest('created_at')
      ->first();
    
    if ($result) {
      $previousResults[$priorPhase] = mb_substr(
        $result->content,
        0,
        $contextLimit
      );
    }
  }
  
  // 3. Retriever context (semantic search from papers)
  $retrieverContext = [];
  if ($phaseNr >= 5) {  // Only P5+ has papers indexed
    $retrieverContext = $this->retrieverService->retrieve(
      query: $projekt->forschungsfrage,
      top_n: 5
    );
  }
  
  // 4. Document counts
  $counts = [
    'paper_embeddings' => DB::selectOne(
      'SELECT COUNT(*) as cnt FROM paper_embeddings WHERE projekt_id = ?',
      [$projekt->id]
    )?->cnt ?? 0,
    'p5_treffer' => DB::selectOne(
      'SELECT COUNT(*) as cnt FROM p5_treffer WHERE projekt_id = ?',
      [$projekt->id]
    )?->cnt ?? 0,
  ];
  
  return [
    'project_metadata'  => $projectContext,
    'previous_results'  => $previousResults,
    'retriever_context' => $retrieverContext,
    'document_counts'   => $counts,
    'phase_label'       => $this->phaseRegistry[$phaseNr]['name'],
  ];
}
```

**Why unified?**
- Eliminates duplicate logic between `agent-action-button.blade.php` and `PhaseChainService.php`
- Single source of truth for context building
- Easy to add new context fields (retriever chunks, metadata, etc.)

---

### 3. Agent Dispatch: `dispatchPhase()`

```php
public function dispatchPhase(int $phaseNr, Projekt $projekt): void {
  
  // Pre-flight checks
  if (!$this->canRunPhase($phaseNr, $projekt)) {
    throw new PhaseNotReadyException(
      "Phase {$phaseNr} preconditions not met"
    );
  }
  
  // Build context
  $context = $this->buildPhaseContext($phaseNr, $projekt);
  
  // Get agent config key
  $agentKey = $this->phaseRegistry[$phaseNr]['agent'];
  
  // Dispatch job (will queue, not block)
  ProcessPhaseAgentJob::dispatch(
    projektId: $projekt->id,
    phaseNr: $phaseNr,
    agentConfigKey: $agentKey,
    context: $context,
  );
  
  // Update UI: mark as 'in_bearbeitung'
  Phase::updateOrCreate(
    ['projekt_id' => $projekt->id, 'phase_nr' => $phaseNr],
    ['status' => 'in_bearbeitung']
  );
}
```

---

### 4. Auto-Chain Handler: `handleAgentCompletion()`

```php
public function handleAgentCompletion(PhaseAgentResult $result): void {
  
  // 1. Quality Gate (Issue #122)
  if (!$this->isValidResult($result)) {
    Log::warning("Phase {$result->phase_nr} result failed quality gate", [
      'projekt_id' => $result->projekt_id,
      'reason'     => $this->getQualityGateFailReason($result),
    ]);
    return;
  }
  
  // 2. Find next phase
  $nextPhase = $this->getNextPhase($result->phase_nr);
  if (!$nextPhase) {
    Log::info("Phase {$result->phase_nr} is final", [
      'projekt_id' => $result->projekt_id,
    ]);
    return;
  }
  
  // 3. Check if next phase can run
  $projekt = $result->projekt;
  if (!$this->canRunPhase($nextPhase, $projekt)) {
    Log::info("Phase {$nextPhase} cannot run yet", [
      'projekt_id' => $projekt->id,
      'reason'     => $this->getBlockingReason($nextPhase, $projekt),
    ]);
    
    // P4→P5 case: notify user to import papers
    if ($nextPhase === 5 && !$this->hasPapers($projekt)) {
      Notification::send(
        $projekt->user,
        new PaperImportRequired($projekt)
      );
    }
    
    return;
  }
  
  // 4. Dispatch next phase (auto-chain)
  Log::info("Auto-dispatching phase {$nextPhase}", [
    'projekt_id' => $projekt->id,
  ]);
  
  $this->dispatchPhase($nextPhase, $projekt);
}
```

**Quality Gate Logic:**
```php
private function isValidResult(PhaseAgentResult $result): bool {
  $content = $result->content ?? '';
  
  // Check 1: Minimum length
  if (strlen($content) < 100) {
    return false;
  }
  
  // Check 2: Not just a confirmation
  if (preg_match('/^(okay|ok|understood|i will|will proceed|acknowledged)/i', $content)) {
    return false;
  }
  
  return true;
}
```

---

## ProcessPhaseAgentJob Refactored

**Before (Scattered Logic):**
- Job dispatch logic spread across Blade + Service
- Message building duplicated
- DB writes ad-hoc

**After (Clean):**

```php
class ProcessPhaseAgentJob implements ShouldQueue {
  
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  public function __construct(
    public string $projektId,
    public int $phaseNr,
    public string $agentConfigKey,
    public array $context,
  ) {}
  
  public function handle(): void {
    try {
      // 1. Call agent with structured output request
      $response = $this->langdockService->call(
        configKey: $this->agentConfigKey,
        messages: $this->buildMessages(),
        structuredOutput: true,  // Expects JSON
      );
      
      // 2. Parse & validate response
      $parsed = $this->parseAndValidate($response);
      
      // 3. Atomic DB transaction
      DB::transaction(function () use ($parsed) {
        $this->writePhaseResult($parsed);
        $this->writeTableData($parsed);
        $this->updatePhaseStatus('abgeschlossen');
      });
      
      // 4. Trigger auto-chain
      ResearchWorkflow::handleAgentCompletion($result);
      
    } catch (Throwable $e) {
      $this->markFailed($e->getMessage());
      Log::error("ProcessPhaseAgentJob failed", [
        'projekt_id' => $this->projektId,
        'phase_nr'   => $this->phaseNr,
        'error'      => $e->getMessage(),
      ]);
    }
  }
  
  private function buildMessages(): array {
    // Now delegates to ResearchWorkflow
    $context = $this->context;  // Already built by orchestrator
    
    return [
      [
        'role'    => 'user',
        'content' => $this->formatContext($context),
      ]
    ];
  }
  
  private function parseAndValidate(string $response): array {
    $data = json_decode($response, true);
    
    // Validate schema
    if (empty($data['phase']) || empty($data['status'])) {
      throw new InvalidResponseException("Missing required fields");
    }
    
    if ($data['phase'] !== $this->phaseNr) {
      throw new InvalidResponseException("Phase mismatch");
    }
    
    if ($data['status'] !== 'completed') {
      throw new InvalidResponseException("Agent did not complete successfully");
    }
    
    return $data;
  }
  
  private function writePhaseResult(array $parsed): void {
    PhaseAgentResult::create([
      'projekt_id'   => $this->projektId,
      'phase_nr'     => $this->phaseNr,
      'agent_config_key' => $this->agentConfigKey,
      'status'       => 'completed',
      'content'      => json_encode($parsed['data']),
      'metadata'     => [
        'duration_ms'    => $parsed['metadata']['duration_ms'] ?? null,
        'tokens_used'    => $parsed['metadata']['tokens_used'] ?? null,
        'db_written'     => $parsed['db_payload'] ?? [],
      ],
    ]);
  }
  
  private function writeTableData(array $parsed): void {
    foreach ($parsed['db_payload']['tables'] ?? [] as $table => $rows) {
      DB::table($table)->insert($rows);
    }
  }
  
  private function updatePhaseStatus(string $status): void {
    Phase::updateOrCreate(
      ['projekt_id' => $this->projektId, 'phase_nr' => $this->phaseNr],
      ['status' => $status, 'abgeschlossen_am' => now()]
    );
  }
  
  private function markFailed(string $error): void {
    PhaseAgentResult::create([
      'projekt_id'   => $this->projektId,
      'phase_nr'     => $this->phaseNr,
      'status'       => 'failed',
      'error_message' => $error,
    ]);
    
    Phase::updateOrCreate(
      ['projekt_id' => $this->projektId, 'phase_nr' => $this->phaseNr],
      ['status' => 'offen']  // Reset to open, user can retry
    );
  }
}
```

---

## Agent Response Format

**What Each Agent Returns (JSON):**

```json
{
  "phase": 5,
  "status": "completed",
  "data": {
    "screening_criteria": [
      {
        "level": "l1",
        "kriterium_typ": "inclusion",
        "beschreibung": "Published in English or German",
        "beispiel": "Peer-reviewed journal articles"
      }
    ],
    "prisma_zahlen": {
      "identifiziert_gesamt": 1000,
      "nach_deduplizierung": 950,
      "eingeschlossen_l1": 850,
      "eingeschlossen_l2": 120,
      "ausgeschlossen_l1": 100,
      "ausgeschlossen_l2": 730
    }
  },
  "db_payload": {
    "tables": {
      "p5_screening_kriterien": [
        {
          "projekt_id": "...",
          "level": "l1",
          "kriterium_typ": "inclusion",
          "beschreibung": "Published in English or German",
          "beispiel": "Peer-reviewed journal articles"
        }
      ],
      "p5_prisma_zahlen": [
        {
          "projekt_id": "...",
          "identifiziert_gesamt": 1000,
          "nach_deduplizierung": 950,
          "eingeschlossen_l1": 850,
          "eingeschlossen_l2": 120,
          "ausgeschlossen_l1": 100,
          "ausgeschlossen_l2": 730
        }
      ]
    }
  },
  "metadata": {
    "duration_ms": 45000,
    "tokens_used": 12500,
    "model": "gpt-4-turbo",
    "next_phase_ready": true,
    "warnings": []
  }
}
```

**Validation in ProcessPhaseAgentJob:**
```php
// Expected table names for P5:
$expectedTables = $this->phaseRegistry[5]['output_tables'];
// ['p5_screening_kriterien', 'p5_prisma_zahlen', ...]

foreach (array_keys($parsed['db_payload']['tables']) as $table) {
  if (!in_array($table, $expectedTables)) {
    throw new InvalidTableException("Unexpected table: {$table}");
  }
}
```

---

## Implementation Roadmap

### Phase 1: Core Orchestration (Week 1)

- [ ] Create `app/Services/ResearchWorkflow.php` with phase registry
- [ ] Implement `canRunPhase()`, `buildPhaseContext()`, `dispatchPhase()`
- [ ] Extract message building into `app/Services/AgentContextBuilder.php`
- [ ] Update `ProcessPhaseAgentJob` to call `ResearchWorkflow`

**Deliverable:** Unified orchestration layer, backward compatible with existing phases

### Phase 2: Structured Output (Week 2)

- [ ] Update Langdock agent configs to request structured JSON output
- [ ] Implement `parseAndValidate()` in `ProcessPhaseAgentJob`
- [ ] Add schema validation for each phase
- [ ] Update agents to return db_payload

**Deliverable:** Agents speak JSON; DB writes are atomic and validated

### Phase 3: Advanced Features (Week 3)

- [ ] Add `handleAgentCompletion()` auto-chain logic
- [ ] Implement P4→P5 pre-flight checks
- [ ] Add WebSocket live updates (result notification)
- [ ] Create phase-specific rollback on DB write failures

**Deliverable:** Production-ready auto-chain with error recovery

### Phase 4: Future Scaling (Week 4+)

- [ ] Parallel batch execution for P5 (screen 1000 papers in parallel)
- [ ] Phase skip/restart capability
- [ ] Human-in-the-loop gates (PI approval before P5→P6)
- [ ] Agent performance metrics dashboard

**Deliverable:** Enterprise-ready workflow orchestration

---

## Benefits Summary

| Aspect | Old Approach | New Hybrid Approach |
|--------|---|---|
| **Phase Dependencies** | Hardcoded in config, scattered checks | Centralized registry, `canRunPhase()` validates |
| **Message Building** | Duplicated in blade + service | Single `AgentContextBuilder` |
| **Agent Outputs** | Free-form text, hard to parse | Structured JSON, schema-validated |
| **DB Writes** | Ad-hoc, no transactions | Atomic, all-or-nothing |
| **P4→P5 Transition** | "User must remember" | `canRunPhase(5)` checks, notifies if papers missing |
| **Error Recovery** | Manual retry | Auto-mark failed, user can re-dispatch |
| **Testing** | Hard to mock full workflow | Easy: test `canRunPhase()`, mock agent JSON |
| **Scalability** | Single linear chain | Foundation for parallelization, batching |

---

## Metrics to Track

After implementation, monitor:

```
✓ Phase completion rate (% reaching P8)
✓ Auto-chain success rate (% where next phase triggered successfully)
✓ Agent token usage per phase (optimize budget allocation)
✓ P4→P5 completion (% of P4 completions that reach P5)
✓ DB write atomicity (failed writes caught before commit)
✓ Quality gate effectiveness (% of results that pass validation)
```

---

## References

- **Current Code:** `app/Jobs/ProcessPhaseAgentJob.php`, `app/Services/PhaseChainService.php`
- **Config:** `config/phase_chain.php`
- **Models:** `app/Models/Recherche/Phase.php`, `PhaseAgentResult.php`
- **UI Components:** `resources/views/livewire/recherche/agent-action-button.blade.php`

