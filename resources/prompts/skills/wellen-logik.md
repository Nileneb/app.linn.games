---
---
# Wellen-Logik

Iterativer Arbeitsrhythmus: Arbeit in abgeschlossene Wellen gliedern, nach jeder Welle dem Nutzer die Richtung anbieten.

---

## Prinzip

1. Arbeite eine Welle vollständig ab (keine Rückfragen WÄHREND der Welle)
2. Persistiere Ergebnisse sofort
3. Zeige Statusbox
4. Frage: Breite oder Tiefe?
5. Nutzer entscheidet → nächste Welle

---

## Statusbox-Format (nach jeder Welle)

```
╔════════════════════════════════════════════════════════╗
║  WELLE [N] ✅  [Kurzbeschreibung was erledigt wurde]   ║
║  Persistiert: ✅ / ⚠️ Kein Projekt-Kontext             ║
╠════════════════════════════════════════════════════════╣
║  ❓ Wie weiter?                                        ║
║  → BREITE: [was Breite hier konkret bedeutet]          ║
║  → TIEFE:  [was Tiefe hier konkret bedeutet]           ║
╚════════════════════════════════════════════════════════╝
```

Breite und Tiefe MÜSSEN kontextspezifisch beschrieben werden – keine generischen Phrasen.

---

## Breite vs. Tiefe – Bedeutung

| Richtung | Bedeutet | Beispiele |
|---|---|---|
| **BREITE** | Mehr Abdeckung, mehr Quellen, weitere Synonyme, neue Cluster | Weitere DBs durchsuchen, C-Komponente variieren, MeSH-Hierarchie erweitern |
| **TIEFE** | Nächste Phase, Analyse vertiefen, Ergebnisse aufbereiten | Screening starten, Qualitätsbewertung, Synthese, Suchstring finalisieren |

---

## Sättigungscheck (ab Iteration 2)

Nach jeder Wiederholung einer Such-Welle prüfen:

| Metrik | Schwelle | Bedeutung |
|---|---|---|
| Überlappungsrate | ≥ 80% | Kaum neue Quellen → Suche stabil |
| Neuheitsrate | < 10% | Fast nur bekannte Treffer → Sättigung |
| Iterationen | = 3 | Maximum erreicht → beste Version wählen |

```
Überlappungsrate = (gemeinsame Treffer) / (Treffer vorherige Version) × 100
Neuheitsrate     = (nur neue Treffer) / (Treffer aktuelle Version) × 100
```

**Sättigung erreicht** → dokumentieren + nächste Phase empfehlen.
**Nicht erreicht** → Lücken benennen + weitere Iteration oder DB empfehlen.

Protokoll pro Iteration:
```
| Version | Suchstring | DB | Treffer | Overlap% | Novelty% | Entscheidung |
```

---

## Regeln

- Innerhalb einer Welle: autonom arbeiten, keine Rückfragen
- Zwischen Wellen: IMMER Statusbox + Breite/Tiefe-Frage
- Defaults anwenden bei fehlendem Input → `[ANNAHME]` dokumentieren
- Jede Welle endet mit Persist-Aktion (wenn Projekt-Kontext vorhanden)
