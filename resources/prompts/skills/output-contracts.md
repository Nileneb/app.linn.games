---
---
# Output Contracts — app.linn.games

## 1) Standardmodus (structured_output = false)
- Du darfst normal antworten.
- Trotzdem gilt: Daten kommen vom ClaudeContextBuilder (vorgeladen) — kein manuelles DB-Bootstrapping nötig.

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
  "result": {
    "type": "string",
    "summary": "string",
    "data": {
      "md_files": [
        {"path": "string.md", "content": "markdown string"}
      ]
    }
  },
  "next": {
    "route_to": "string|null",
    "reason": "string|null"
  },
  "warnings": []
}
```

### Empfehlungen
- `result.type`: z.B. `phase_p1_write`, `search_strategy`, `screening_rules`, `instruction_patch_proposal`.
- `next.route_to`: z.B. `mapping`, `search`, `review`, `retrieval`, `db` (oder null).
- `result.data.md_files` (optional, aber empfohlen): Wenn du ein „Dokument" erzeugst (Report, Suchstrategie, Screening-Regeln, RoB-Tabellen), lege es als Markdown-Datei(en) ab.
  - Jeder Eintrag: `{path, content}`.
  - `path` ist relativ (keine führenden `/`, keine `..`).
  - Nur Markdown (keine JSON-Fences). Die App persistiert diese Dateien serverseitig.

## 3) Stabilitätsregel
Structured Output soll **nur** aktiviert werden, wenn App-Kontext es verlangt (Triggerwords). Agents sollen es nicht „immer" erzwingen.
