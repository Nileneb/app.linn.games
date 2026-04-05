---
name: langdock-triggerwords-protocol
description: "Triggerword-Routing in app.linn.games: Syntax, Mapping zu Agenten, und was der Agent davon (nicht) sieht."
argument-hint: "Nenne das Triggerword und den Task; optional projekt_id UUID."
---

# Triggerwords — Protokoll (app.linn.games)

Dieses Skill beschreibt, wie die App Chat-Nachrichten **deterministisch** zu Langdock-Agents routet.

## 1) Syntax (User-Eingabe)
- Triggerword ist das **erste Token** und beginnt mit `@` oder `#`.
- Optional kann das **zweite Token** eine UUID sein → wird als `projekt_id` interpretiert.
- Der Rest ist die eigentliche Nachricht.

Beispiele:
- `@mapping 3f3b...-... Bitte erstelle PICO Komponenten …`
- `#search Bitte baue einen PubMed Suchstring …`

## 2) Mapping Triggerword → config key
- `mapping` → `scoping_mapping_agent`
- `search` → `search_agent`
- `review` → `review_agent`
- `retrieval` → `retrieval_agent`
- `evaluation` / `bewertung` → `evaluation_agent`
- `mayring` → `mayring_agent`
- `synthesis` → `synthesis_agent`
- `report` → `synthesis_agent`
- `pico` → `pico_agent`
- `db` → `agent_id`

## 3) Wichtig für Agents
- Der Trigger wird **aus dem User-Text entfernt**, bevor er an Langdock geht.
- Du bekommst den Trigger nur im Kontext-Feld `triggerword`.
- Bei erkanntem Trigger setzt die App `structured_output=true` (JSON Envelope v1).

## 4) Design-Implication
- Agents sollen nicht versuchen, Triggerwords „zu parsen“.
- Sie sollen anhand des Kontextes (triggerword/phase_nr/projekt_id/workspace_id) arbeiten.
