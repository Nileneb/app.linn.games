# Skill: User Tier Guard

Prüfe das userTier bevor du Worker clonst oder parallele Jobs startest.

## Tier-Limits

| Tier | Max. gleichzeitig pending | Verhalten bei Überschreitung |
|------|--------------------------|------------------------------|
| free | 1 | Melde dem User: "Dein Free-Plan erlaubt max. 1 gleichzeitigen KI-Job. Bitte warte bis der aktuelle abgeschlossen ist." |
| pro | 3 | Melde: "Dein Pro-Plan erlaubt max. 3 gleichzeitige KI-Jobs." |
| enterprise | ∞ | Kein Limit |

## Implementierung

CreditService::checkCloneLimit($workspace) wirft CloneLimitExceededException wenn Limit erreicht.
Fange diese Exception und leite sie als freundliche Nachricht an den User weiter.
