---
---
# Searchterm-Syntax

Regeln für den Aufbau valider, reproduzierbarer Suchstrings in akademischen Datenbanken.

---

## 1. Boolesche Operatoren

IMMER großgeschrieben. Keine Ausnahmen.

| Operator | Funktion | Beispiel |
|---|---|---|
| `AND` | Alle Begriffe müssen vorkommen (Schnittmenge) | `"nursing homes" AND "artificial intelligence"` |
| `OR` | Mindestens ein Begriff muss vorkommen (Vereinigung) | `"care home" OR "nursing home" OR "assisted living"` |
| `NOT` | Begriff ausschließen | `NOT ("animal experimentation"[MeSH Terms] NOT "humans"[MeSH Terms])` |

**Auswertungsreihenfolge:** Klammern > NOT > AND > OR.
→ Immer Klammern setzen, um Reihenfolge explizit zu machen.

**Kombinationslogik pro Suchstrategie:**
```
(Konzept-A-Synonyme mit OR)
AND
(Konzept-B-Synonyme mit OR)
AND
(Konzept-C-Synonyme mit OR)  ← optional
```

---

## 2. Trunkierung & Wildcards

| Zeichen | Funktion | Beispiel | Findet |
|---|---|---|---|
| `*` | Beliebig viele Zeichen am Wortende | `nurs*` | nurse, nurses, nursing |
| `?` | Genau 1 Zeichen (nur manche DBs) | `wom?n` | woman, women |
| `#` | 0 oder 1 Zeichen (nur manche DBs) | `colo#r` | color, colour |

**Regeln:**
- Trunkierung frühestens nach 3–4 Zeichen (sonst zu viel Noise)
- Nie bei MeSH-Terms trunkieren – MeSH ist kontrolliertes Vokabular
- Trunkierung nur bei Freitext-Feldern (`[TIAB]`, `[TI]`)

---

## 3. Phrasensuche

Exakte Mehrwortbegriffe in `"Anführungszeichen"`:
```
"myocardial infarction"
"artificial intelligence"
"quality of life"
```
Ohne Anführungszeichen werden Wörter einzeln gesucht → zu viele Treffer.

---

## 4. Feldcodes (PubMed als Referenz)

| Feldcode | Bedeutung | Einsatz |
|---|---|---|
| `[MeSH Terms]` | Kontrolliertes Vokabular (MeSH-Deskriptor) | Validierte Fachbegriffe |
| `[TIAB]` | Titel + Abstract (Freitext) | Synonyme, neue Begriffe ohne MeSH |
| `[TI]` | Nur Titel | Hochpräzise Suche |
| `[AU]` | Autor | Autorensuche |
| `[pt]` | Publikationstyp | `"randomized controlled trial"[pt]` |
| `[dp]` | Erscheinungsdatum | `"2015/01/01"[dp] : "2026/12/31"[dp]` |
| `[lang]` | Sprache | `English[lang]` |
| `[Majr]` | MeSH Major Topic (Hauptthema) | Engere Suche als `[MeSH Terms]` |
| `[Subheading]` | MeSH-Qualifikator | `"Neoplasms/therapy"[MeSH Terms]` |

---

## 5. Kontrolliertes Vokabular

### MeSH (Medical Subject Headings) – PubMed / MEDLINE
- Hierarchischer Thesaurus der NLM
- Jeder Artikel wird manuell mit MeSH-Terms indexiert
- Explosion: Ein MeSH-Term schließt automatisch alle Unterbegriffe ein
- `"Neoplasms"[MeSH Terms]` findet auch Artikel über Brustkrebs, Lungenkrebs etc.
- Explosion deaktivieren: `"Neoplasms"[MeSH Terms:noexp]`

### Emtree – Embase
- Äquivalent zu MeSH, aber umfangreicher (besonders Arzneimittel, Medizinprodukte)
- Syntax: `/exp` für Explosion, `/de` für exakt
- `'artificial intelligence'/exp` → inkl. aller Unterbegriffe

### Thesaurus – PsycINFO, ERIC, andere
- Datenbankspezifische kontrollierte Vokabulare
- Vor Nutzung immer im jeweiligen DB-Thesaurus nachschlagen

**Goldene Regel:** Immer MeSH/Thesaurus UND Freitext kombinieren:
```
("Artificial Intelligence"[MeSH Terms] OR "artificial intelligence"[TIAB] OR "machine learning"[TIAB] OR "deep learning"[TIAB])
```
→ Kontrolliertes Vokabular fängt korrekt indexierte Artikel.
→ Freitext fängt neue/noch nicht indexierte Artikel.

---

## 6. Filter

Nur einsetzen wenn nötig – jeder Filter reduziert Sensitivität.

| Filter | PubMed-Syntax | Wann nutzen |
|---|---|---|
| Zeitraum | `"2015"[dp] : "2026"[dp]` | Bei zeitlich eingegrenzter Frage |
| Sprache | `English[lang] OR German[lang]` | Bei Sprachbeschränkung |
| Studientyp | `"randomized controlled trial"[pt]` | Bei PICO / Wirksamkeitsfrage |
| Nur Humans | `"humans"[MeSH Terms]` | Standard bei klinischen Fragen |
| Ausschluss Tiere | `NOT ("animals"[MeSH Terms] NOT "humans"[MeSH Terms])` | Standardfilter |
| Altersgruppe | `"aged"[MeSH Terms]` | Bei populationsspezifischer Frage |
| Review-Artikel | `"systematic review"[pt] OR "meta-analysis"[pt]` | Für Sekundärliteratur |

---

## 7. Suchstring-Aufbau (Template)

```
── Konzept A (z.B. Population) ──────────────────────────
(FreitextA1[TIAB] OR FreitextA2[TIAB] OR "MeSH-A"[MeSH Terms])

AND

── Konzept B (z.B. Intervention) ────────────────────────
(FreitextB1[TIAB] OR FreitextB2[TIAB] OR "MeSH-B"[MeSH Terms])

AND

── Konzept C (z.B. Outcome, optional) ──────────────────
(FreitextC1[TIAB] OR FreitextC2[TIAB] OR "MeSH-C"[MeSH Terms])

── Filter (optional) ───────────────────────────────────
AND ("2015"[dp] : "2026"[dp])
AND (English[lang] OR German[lang])
NOT ("animals"[MeSH Terms] NOT "humans"[MeSH Terms])
```

---

## 8. Qualitätscheckliste vor Ausführung

- [ ] Boolesche Operatoren großgeschrieben?
- [ ] Klammern korrekt gesetzt (jedes Konzept eingeklammert)?
- [ ] Phrasen in Anführungszeichen?
- [ ] MeSH-Terms validiert (existieren in der Ziel-DB)?
- [ ] Freitext UND kontrolliertes Vokabular kombiniert?
- [ ] Trunkierungen sinnvoll (nicht zu kurz, nicht bei MeSH)?
- [ ] Filter nur wo nötig?
- [ ] Britische + amerikanische Schreibweisen berücksichtigt?

---

## 9. Syntax-Unterschiede zwischen Datenbanken

| Feature | PubMed | Scopus | Web of Science | Embase |
|---|---|---|---|---|
| Kontrolliertes Vokabular | `[MeSH Terms]` | – (kein Thesaurus) | – | `/exp`, `/de` |
| Freitext Titel+Abstract | `[TIAB]` | `TITLE-ABS-KEY()` | `TS=` | `:ti,ab` |
| Trunkierung | `*` | `*` | `*` | `*` |
| Phrasensuche | `"..."` | `"..."` | `"..."` | `'...'` |
| Proximity | nicht nativ | `W/n`, `PRE/n` | `NEAR/n` | `NEAR/n` |
| Publikationstyp | `[pt]` | `DOCTYPE()` | `DT=` | `/lim` |

Bei Suche über mehrere DBs: PubMed-String als Master bauen, dann pro DB anpassen.
