# Issue #136 Tasks 3-5 Plan (Worktree)

## Task 3: Environment Configuration

**Files to Modify:**
- `.env.example` — Add new env vars with comments
- `config/services.php` — Register LANGDOCK_WEBHOOK_SECRET (already done in fe717e3, verify)

**Env Vars to Add to .env.example:**
```env
# Master Agent for Simplified Pipeline (Issue #136)
MASTER_RESEARCH_AGENT=<UUID from Langdock>

# Webhook Security
LANGDOCK_WEBHOOK_SECRET=<generated-secret-key>
```

**Status:** Low priority — just documentation, already partially done in fe717e3

---

## Task 4: Integration Test — End-to-End

**File to Create:**
- `tests/Feature/Integration/AgentPipelineE2ETest.php`

**Test Scenario:**
1. Create Projekt with Owner
2. Call `POST /api/webhooks/langdock/agent-result` with valid webhook payload
3. Verify markdown files written to storage
4. Call `GET /recherche/{projekt}/ergebnisse/{phase}` as Owner
5. Verify HTML response contains rendered markdown
6. Call same route as non-owner → 403
7. Call with invalid phase → 404

**No new code needed** — just test the integration of Tasks 1+2

---

## Task 5: Verify Test Suite

**Commands:**
```bash
# Local (no Docker)
composer test

# Docker (recommended)
docker compose run --rm php-test vendor/bin/pest
```

**Acceptance:**
- ✅ All existing tests (247) still pass
- ✅ New webhook handler tests (3) pass
- ✅ New markdown viewer tests (4) pass
- ✅ Integration test (1) passes
- Total: 255+ tests

**Definition of Done:**
- All tests green ✅
- No warnings or errors
- Code ready to merge to main

---

## Implementation Order

1. **Task 3** (5 min) — Update .env.example + verify config
2. **Task 4** (15 min) — Write integration test
3. **Task 5** (5 min) — Run full test suite, verify passing

**Goal:** All tests green → Exit worktree → Merge to main
