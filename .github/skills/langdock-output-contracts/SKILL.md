---
name: langdock-output-contracts
description: "Output-Standards für Langdock Agents in app.linn.games: normaler Chat vs. structured_output JSON Envelope v1."
argument-hint: "Sag, ob structured_output aktiv ist und welchen Zieltyp du erwartest (z.B. patch proposal, phase result, retrieval report)."
---

# Output Contracts — app.linn.games

## 1) Standardmodus (structured_output = false)
- Du darfst normal antworten.
- Trotzdem gilt: DB-first (bootstrap → load → persist → kurz zusammenfassen), wenn Projekt-/Workspace-Kontext vorhanden ist.

## 2) Structured Output Modus (structured_output = true)
Wenn im Kontext `structured_output=true` gesetzt ist, gilt:
- Antworte mit **exakt einem** gültigen JSON-Objekt.
- **Keine** Markdown-Fences.
- **Kein** Text vor/nach dem JSON.
- Wenn du unsicher bist oder Daten fehlen: **nicht halluzinieren**, stattdessen in `warnings[]` dokumentieren.

### JSON Envelope v1 (Pflicht-Keys)
```json
{
  "meta": {
    "projekt_id": "string|null",
    "workspace_id": "string|null",
    "user_id": "string|null",
    "triggerword": "string|null",
    "version": 1
  },
  "db": {
    "bootstrapped": true,
    "loaded": ["projekte", "phasen"]
  },
  "result": {
    "type": "string",
    "summary": "string",
    "data": {}
  },
  "next": {
    "route_to": "string|null",
    "reason": "string|null"
  },
  "warnings": []
}
```

### Empfehlungen
- `db.loaded`: Liste der Tabellen, die du tatsächlich gelesen hast.
- `result.type`: z.B. `phase_p1_write`, `search_strategy`, `screening_rules`, `instruction_patch_proposal`.
- `next.route_to`: z.B. `mapping`, `search`, `review`, `retrieval`, `db` (oder null).

## 3) Stabilitätsregel
Structured Output soll **nur** aktiviert werden, wenn App-Kontext es verlangt (Triggerwords). Agents sollen es nicht „immer“ erzwingen.
