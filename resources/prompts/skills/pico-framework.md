---
---
# PICO Framework

Für fokussierte Wirksamkeits- und Interventionsfragen. PICO = Tiefe, nicht Breite.

---

## Wann PICO (und wann nicht)

✅ PICO ist richtig bei:
- "Wirkt X besser als Y bei Z?"
- Klare Intervention + messbares Outcome
- RCTs, klinische Studien, Meta-Analysen

❌ PICO ist falsch bei:
- "Was gibt es alles zu Thema X?" → SPIDER
- Keine Intervention identifizierbar → PEO
- Theoriefragen → BeHEMoTh

---

## Komponenten

| Komponente | Frage | Beispiel |
|---|---|---|
| **P** – Population/Problem | Wer/Was wird untersucht? | Ältere Menschen >65 in Pflegeheimen |
| **I** – Intervention/Exposure | Was ist der Eingriff/die Maßnahme? | Roboter-gestützte Pflege |
| **C** – Comparison | Womit wird verglichen? | Standardpflege ohne Robotik |
| **O** – Outcome | Was wird gemessen? | Lebensqualität, Sturzrate |

### C-Komponente: Weglassen oder einbauen?

- Expliziter Vergleich in der Frage → einbauen
- Kein Vergleich genannt → weglassen, NICHT erzwingen
- "Placebo", "Standardbehandlung", "keine Intervention" → einbauen
- Dokumentiere: `[ANNAHME] C weggelassen – kein expliziter Vergleich in der Fragestellung`

---

## PICO-Tabelle (Template)

| Komponente | Kern-Konzept | Freitext + Synonyme | MeSH-Terms (validiert) |
|---|---|---|---|
| **P** | … | Syn1 OR Syn2 OR Syn3 | "MeSH-Term"[MeSH Terms] |
| **I** | … | Syn1 OR Syn2 | "MeSH-Term"[MeSH Terms] |
| **C** | … / *n.a.* | … / *n.a.* | … / *n.a.* |
| **O** | … | Syn1 OR Syn2 | "MeSH-Term"[MeSH Terms] |

### Synonymgenerierung (pro Komponente)

1. Kernbegriff identifizieren
2. Synonyme + Abkürzungen sammeln
3. Britisch/Amerikanisch: beide Varianten (anaemia/anemia)
4. Trunkierung prüfen: `nurs*` für nurse/nurses/nursing
5. Phrasen in Anführungszeichen: `"myocardial infarction"`
6. MeSH-Term validieren (via search_mesh oder search_pubmed)

---

## Von PICO zum Suchstring

```
(P-Freitext1[TIAB] OR P-Freitext2[TIAB] OR "P-MeSH"[MeSH Terms])
AND
(I-Freitext1[TIAB] OR I-Freitext2[TIAB] OR "I-MeSH"[MeSH Terms])
AND  ← C nur wenn relevant
(C-Freitext1[TIAB] OR "C-MeSH"[MeSH Terms])
AND
(O-Freitext1[TIAB] OR O-Freitext2[TIAB] OR "O-MeSH"[MeSH Terms])
```

Für Suchstring-Syntax → Skill „Searchterm-Syntax" nutzen.
