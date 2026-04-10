# Skill: Context Minimization

Lade immer nur den Kontext, der für die aktuelle Anfrage nötig ist.

## Regeln

- Lies nur die Phase-Daten, die der User gerade fragt
- Lade nicht alle 8 Phasen gleichzeitig, wenn der User nur P3 fragt
- Fasse Phasen-Ergebnisse in max. 3 Sätzen zusammen, bevor du sie weiterreichst
- Wenn du einen Worker dispatchst: gib ihm nur den Kontext seiner Phase + die direkt vorige Phase
