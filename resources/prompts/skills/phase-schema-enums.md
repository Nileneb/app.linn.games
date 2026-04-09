---
---
# Phase-Schema & Enums — app.linn.games

Dieses Skill liefert die **Referenz** für:
- welche Tabellen pro Phase relevant sind,
- welche Tabellen **kein** `projekt_id` haben (Sonderfälle),
- welche PostgreSQL Enums existieren und welche Werte erlaubt sind.

## 1) Phase-Tabellen (Namen-Orientierung)
Hinweis: In der App werden pro Phase häufig konkrete Schema-Snippets in den System-Context injiziert. Wenn du ein Schema-Snippet erhältst, ist es die **Source of Truth**.

### P1
- `p1_strukturmodell_wahl`
- `p1_komponenten`
- `p1_kriterien`
- `p1_warnsignale`

### P2
- `p2_review_typ_entscheidung`
- `p2_cluster`
- `p2_trefferlisten`
- `p2_mapping_suchstring_komponenten`

### P3
- `p3_datenbankmatrix`
- `p3_disziplinen`
- `p3_geografische_filter`
- `p3_graue_literatur`

### P4
- `p4_suchstrings`
- `p4_thesaurus_mapping`
- `p4_anpassungsprotokoll`

### P5
- `p5_treffer`
- `p5_screening_kriterien`
- `p5_screening_entscheidungen`
- `p5_prisma_zahlen`
- `p5_tool_entscheidung`

### P6
- `p6_qualitaetsbewertung`
- `p6_luckenanalyse`

### P7
- `p7_synthese_methode`
- `p7_datenextraktion`
- `p7_muster_konsistenz`
- `p7_grade_einschaetzung`

### P8
- `p8_suchprotokoll`
- `p8_limitationen`
- `p8_reproduzierbarkeitspruefung`
- `p8_update_plan`

## 2) Kritische Sonderfälle: Tabellen ohne projekt_id
Diese Tabellen haben **kein** direktes `projekt_id`. Du musst über den jeweiligen FK arbeiten:
- `p4_anpassungsprotokoll` → FK: `suchstring_id` → `p4_suchstrings(id)`
- `p5_screening_entscheidungen` → FK: `treffer_id` → `p5_treffer(id)`
- `p6_qualitaetsbewertung` → FK: `treffer_id` → `p5_treffer(id)`
- `p7_datenextraktion` → FK: `treffer_id` → `p5_treffer(id)`
- `p8_suchprotokoll` → FK: `suchstring_id` → `p4_suchstrings(id)` (zusätzlich `projekt_id` vorhanden)

## 3) PostgreSQL Enums (Werte)
Diese Enums existieren DB-seitig und sind strikt:
- `phase_status`: `offen`, `in_bearbeitung`, `abgeschlossen`
- `review_typ`: `systematic_review`, `scoping_review`, `evidence_map`
- `strukturmodell`: `PICO`, `SPIDER`, `PICOS`
- `kriterium_typ`: `einschluss`, `ausschluss`
- `screening_level`: `L1_titel_abstract`, `L2_volltext`
- `screening_entscheidung`: `eingeschlossen`, `ausgeschlossen`, `unklar`
- `rob_tool`: `RoB2`, `ROBINS-I`, `CASP_qualitativ`, `AMSTAR2`, `ROBINS-I_erweitert`, `narrativ`, …
- `rob_urteil`: `niedrig`, `moderat`, `hoch`, `kritisch`, `nicht_bewertet`
- `synthese_methode`: `meta_analyse`, `narrative_synthese`, `thematische_synthese`, `framework_synthesis`, …
- `grade_urteil`: `stark`, `moderat`, `schwach`, `sehr_schwach`
- `studientyp`: `RCT`, `nicht_randomisiert`, `qualitativ`, `systematic_review`, `guideline_framework`, `konzeptuell`, …
- `tool_empfehlung`: `Rayyan`, `Covidence`, `EPPI_Reviewer`, `DistillerSR`, `ASReview`, `SWIFT_ActiveScreener`, …

## 4) Praktische Regel
- Wenn du bei INSERT/UPDATE Enum-Fehler bekommst: erst die erlaubten Werte per Kontext/Skill prüfen, dann korrigieren.
