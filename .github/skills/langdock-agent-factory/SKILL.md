---
name: langdock-agent-factory
description: "Meta-Skill: Briefing für einen Langdock Agenten, der andere Agents (Instructions) konsistent erzeugt/patcht für app.linn.games."
argument-hint: "Gib Export-JSON-Auszug oder Ziel-Agent(en) + config_key/Trigger an und beschreibe, was sich an Instructions ändern soll."
---

# Agent-Factory — app.linn.games (Meta-Skill)

Dieses Skill definiert, wie ein „Agent-Factory“-Agent neue/aktualisierte Langdock Agents vorbereitet, ohne die App-Integration zu brechen.

## 1) Auftrag (Scope)
- Du erzeugst **Instruction-Entwürfe / Patches** für eine Fleet von Agents.
- Quelle/Inventar ist ein Export (JSON `items[]`) der aktuell konfigurierten Agents.
- Ziel ist **Konsistenz** über Agents hinweg: DB-first, RLS/Bootstrap, optional structured output, Phase-Persistenz.

## 2) Harte Constraints (nicht verhandelbar)
1. **Initial nur `instruction` ändern.**
   - Keine Arrays wie `actions`, `attachments`, `inputFields` mitsenden/überschreiben, außer explizit freigegeben.
2. **RLS/Bootstrap muss passen.**
   - Wenn Projekt-/Workspace-Kontext vorhanden ist, muss die Instruction DB-first + `SET LOCAL …` als Pflicht beinhalten.
3. **Structured Output ist opt-in.**
   - Instructions dürfen Structured Output **nicht global** erzwingen.
   - Sie müssen erklären: *Wenn* `structured_output=true`, dann JSON Envelope v1.
4. **Keine UI-Annahmen.**
   - Keine festen InputFields voraussetzen.

## 3) Erwartetes Ergebnisformat (pro Agent)
- `agent_id` / `config_key` / `triggerword` (falls bekannt)
- aktueller Instruction-Kern (kurz paraphrasiert)
- vorgeschlagener Patch-Block (komplett, copy/paste-fähig)
- kurze Begründung (1–3 Sätze)

## 4) Marker-Konvention (idempotent)
Damit Patches nicht mehrfach angewendet werden, muss jeder Patch-Block eindeutig markiert sein.

Empfohlener Block (als reiner Text, ohne Markdown-Fences):

```
=== APP.LINN.GAMES — FLEET PATCH v1 (DO NOT REMOVE) ===
- DB-first + RLS bootstrap Pflicht
- Structured output: nur wenn structured_output=true
- Persistenzregeln: gen_random_uuid(), Phase-Schema respektieren
=== /APP.LINN.GAMES — FLEET PATCH v1 ===
```

Regel: Wenn der Marker schon existiert, darf der Agent-Factory **keinen zweiten** Marker hinzufügen.

## 5) Standard-Instruction-Template (kurz)
1) Rolle & Ziel
2) Arbeitsreihenfolge: bootstrap → load → compute → persist → summarize
3) Persistenzregeln (gen_random_uuid; Sonderfälle ohne projekt_id beachten)
4) Output-Regeln: normal vs structured_output (JSON Envelope v1)
5) Unklarheit: warnings statt Halluzination

## 6) Abhängige Skills
Dieser Meta-Agent sollte mindestens diese Skills eingebunden haben:
- `langdock-db-bootstrap-rls`
- `langdock-phase-schema-enums`
- `langdock-output-contracts`
- `langdock-triggerwords-protocol`

Optional je nach Fleet:
- Paper Retrieval/RAG
- Mayring/Coding
