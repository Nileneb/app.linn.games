---
---
# PRISMA-Tracking

PRISMA-Zahlen werden NICHT manuell gezählt. Nutze das Python-Skript `prisma_calculator.py`.

---

## Prinzip

KI zählt schlecht. Deshalb:
- Agent sammelt nur die Roh-Variablen
- Python-Skript berechnet alle abgeleiteten Werte
- Skript validiert Konsistenz und warnt bei Fehlern

---

## Skript aufrufen

```bash
python3 prisma_calculator.py \
    --identified '{"pubmed": 342, "semantic": 289, "arxiv": 45}' \
    --duplicates 127 \
    --excluded_screening 580 \
    --excluded_fulltext '{"wrong_population": 12, "wrong_intervention": 8, "wrong_outcome": 5}' \
    --included 34 \
    --awaiting_retrieval 7 \
    --output both
```

### Parameter

| Parameter | Typ | Pflicht | Beschreibung |
|---|---|---|---|
| `--identified` | JSON dict | ✅ | Treffer pro Datenbank: `{"db": n, ...}` |
| `--duplicates` | int | ❌ (default 0) | Entfernte Duplikate |
| `--excluded_screening` | int | ❌ (default 0) | Ausgeschlossen nach Titel/Abstract |
| `--excluded_fulltext` | JSON dict | ❌ (default {}) | Ausschlussgründe Volltext: `{"grund": n, ...}` |
| `--included` | int | ❌ | Final eingeschlossen (überschreibt Berechnung) |
| `--awaiting_retrieval` | int | ❌ (default 0) | Volltext noch ausstehend |
| `--output` | json/text/both | ❌ (default both) | Ausgabeformat |

### Output

- **JSON**: Strukturierte Daten für DB-Persistierung
- **Text**: ASCII-Flowdiagramm für Chat-Ausgabe
- **Validierung**: Automatische Konsistenz-Warnungen (⚠️)

---

## Wann aufrufen

| Phase | Aktion |
|---|---|
| Nach P4 (Suche) | `--identified` + `--duplicates` setzen, Rest [OFFEN] |
| Nach P5 L1 (Titel/Abstract) | `--excluded_screening` ergänzen |
| Nach P5 L2 (Volltext) | `--excluded_fulltext` + `--included` ergänzen |
| Ende P8 (Bericht) | Finaler Aufruf mit allen Werten, in Report einbetten |

Das Skript kann inkrementell aufgerufen werden – unbekannte Felder werden als [OFFEN] markiert.

---

## Validierungen (automatisch)

- Duplikate > Identifiziert → ⚠️
- Ausgeschlossen > Gescreent → ⚠️
- Included manuell ≠ berechnet → ⚠️ mit Differenz
- Negative Werte → ⚠️

---

## DB-Persistierung

JSON-Output direkt in die DB schreiben:
- `p5_*`: PRISMA-Zahlen nach Screening
