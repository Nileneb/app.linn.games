---
name: langdock-db-bootstrap-rls
description: "DB-Bootstrap + RLS-Regeln für Langdock Agents in app.linn.games (execute_sql + SET LOCAL Pflicht)."
argument-hint: "Beschreibe den Task (Phase/Projekt/Workspace) und welche DB-Operationen nötig sind."
---

# DB Bootstrap & RLS — app.linn.games (Pflicht-Skill)

Dieses Skill ist die **harte Sicherheits- und Funktionsgrundlage** für alle Langdock-Agents, die in app.linn.games mit PostgreSQL (RLS) arbeiten.

## 1) Grundregel: RLS ist aktiv
- Ohne korrekt gesetzte Session-Variable siehst du **0 Zeilen** und Writes schlagen **still fehl**.
- Daher gilt:

**Jeder** `execute_sql`-Call muss **im selben SQL-Block** als **erste Anweisung** mit `SET LOCAL …` beginnen.

### Projekt-Kontext
```sql
SET LOCAL app.current_projekt_id = '<projekt_uuid>';
-- danach erst SELECT/INSERT/UPDATE/DELETE
```

### Workspace-Kontext
```sql
SET LOCAL app.current_workspace_id = '<workspace_uuid>';
```

## 2) DB-first Arbeitsreihenfolge (immer)
1. Bootstrap (`SET LOCAL …`)
2. Arbeitsgrundlage laden (mindestens `projekte` + `phasen`, plus relevante `p*`-Tabellen)
3. Ergebnisse berechnen
4. Ergebnisse **persistieren** (INSERT/UPDATE)
5. Kurz zusammenfassen (oder JSON Envelope, falls aktiviert)

## 3) Persistenz-Konventionen
- UUID-PKs werden mit `gen_random_uuid()` erzeugt.
- Domain-Tabellen haben i.d.R. `projekt_id` als FK (Ausnahmen siehe Phase-Schema-Skill).

Beispiel INSERT:
```sql
SET LOCAL app.current_projekt_id = '<projekt_uuid>';
INSERT INTO p1_kriterien (id, projekt_id, kriterium_typ, beschreibung)
VALUES (gen_random_uuid(), '<projekt_uuid>', 'einschluss', '...');
```

## 4) Umgang mit Unsicherheit
- Wenn du nicht sicher bist, welche Tabelle/Spalte gemeint ist: **erst DB lesen** (Schema-/Beispieldaten via SELECT), dann entscheiden.
- Niemals raten/halluzinieren; fehlende Infos werden (falls structured output aktiv) als `warnings[]` zurückgegeben.

## 5) Grenzen dieses Skills
- Dieses Skill definiert **nur** Bootstrap/RLS/Persistenz-Grundregeln.
- Tabellen/Enums/Output-Format sind in separaten Skills dokumentiert.
