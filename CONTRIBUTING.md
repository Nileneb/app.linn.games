# Contributing — app.linn.games

## Branch-Konvention

| Prefix | Zweck | Beispiel |
|---|---|---|
| `feature/` | Neues Feature | `feature/ollama-embedding` |
| `fix/` | Bugfix | `fix/job-deduplication` |
| `docs/` | Nur Dokumentation | `docs/update-readme` |
| `refactor/` | Keine funktionale Änderung | `refactor/model-cleanup` |

## Merge-Fluss

```
feature/* / fix/*  →  develop  →  main (Deploy)
```

- **Kein Direkt-Merge auf `main`** — immer über `develop`.
- PRs benötigen grüne CI-Checks (Tests + Lint).
- Squash-Merge bevorzugt (saubere Historie).

## Arbeitsablauf pro Issue

1. Issue auswählen und sich selbst zuweisen
2. Branch erstellen: `git checkout -b feature/issue-42-kurzbeschreibung develop`
3. Implementieren in kleinen Commits
4. Lokale Tests: `composer test`
5. PR erstellen → PR-Template ausfüllen → `Closes #42`
6. CI abwarten → Review einholen → Merge

## Lokale Entwicklung

```bash
composer setup   # Install, Key, Migrate, NPM Build
composer dev     # Server + Queue + Vite (parallel)
composer test    # Pest Tests
```

## Coding-Standards

- **PHP:** Laravel Pint (wird in CI geprüft)
- **Datenbank:** PostgreSQL, UUID-Primärschlüssel
- **Livewire:** Volt Inline-Komponenten (PHP-Klasse im Blade-File)
- **Admin:** Filament 4.9 (Schemas\Schema für Formulare)

## Datenbank-Änderungen

- Migration **immer** in separatem Commit vor Code-Changes
- Rollback-Strategie im PR dokumentieren
- `langdock_agent`-User-Rechte bei neuen Tabellen berücksichtigen

## Tests

- Neue Features brauchen mindestens einen Feature-Test
- Bugfixes brauchen einen Regressionstest
- Tests laufen mit SQLite in-memory (`phpunit.xml`)
