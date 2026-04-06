
**Mögliche Engpässe** (die üblichen Verdächtigen):

1. **P4 noch nicht sauber abgeschlossen** – Wenn die Suchstrategie (Searchterms, Datenbank-Auswahl) in `p4_*` nicht vollständig persistiert ist, hat der Search Agent nichts zum Arbeiten.
2. **Screening (P5) blockiert** – Der Review Agent wartet auf Treffer in `p5_treffer`, aber die wurden nie geschrieben.
3. **Retrieval-Kette hängt** – Der Retrieval Agent versucht PDFs zu beschaffen, aber die Fallback-Kette (DB → OA → manuell) liefert nichts.
4. **Fehlende Vorphasen** – Kein PICO (P1), kein Mapping (P2) → dann fehlt die Grundlage für alles.

>>> was dauert beim scoping so lange?? 

