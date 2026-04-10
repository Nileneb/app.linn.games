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
