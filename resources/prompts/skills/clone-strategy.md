# Skill: Clone Strategy

Erkenne stuck Workers und entscheide ob und wie du clonst.

## Wann ist ein Worker stuck?

1. **Timeout:** PhaseAgentResult hat status='pending' seit > 10 Minuten
2. **Quality Gate:** isValidPhaseResult() schlug 3x fehl (content < 100 Zeichen oder nur Bestätigungen)
3. **Exception:** ClaudeAgentException nach allen Retries

## Clone-Strategie auswählen

- `retry`: gleiche Messages, neuer Job → bei Timeout oder ConnectionException
- `rephrase`: füge dem System-Prompt hinzu: "Vorheriger Versuch fehlgeschlagen. Formuliere deine Antwort anders. Konzentriere dich auf strukturierte Markdown-Ausgabe." → bei Quality Gate Failure

## Vorgehen

1. Prüfe userTier via user-tier-guard Skill
2. Wenn Limit nicht erreicht: dispatch neuen ProcessPhaseAgentJob mit clone_strategy
3. Logge: welche Phase, welche Strategie, welcher Attempt-Count
