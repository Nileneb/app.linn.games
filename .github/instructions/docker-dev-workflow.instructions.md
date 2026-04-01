---
applyTo: "docker-compose.yml,docker/**,Dockerfile*"
---

# Docker Dev-Workflow — app.linn.games

## Architektur

10 Container: `web`, `php-fpm`, `php-cli`, `php-test`, `queue-worker`, `postgres`, `redis`, `postgres-mcp`, `ollama`, `mcp-paper-search`

**Code-Deployment**: PHP-Code wird beim Docker-Build via `COPY . /var/www` in die Images gebaked (`php-fpm`, `php-cli`, `queue-worker`). Änderungen am PHP-Code erfordern immer einen Rebuild.

**Static Assets**: `./public` ist als `:ro` Bind-Mount in `web` (nginx) eingebunden. Änderungen an `public/` (CSS, JS, Filament-Assets) sind sofort wirksam — **kein nginx-Rebuild nötig**.

Ausnahme: `public/build` wird zusätzlich vom named Volume `linn-build-assets` überschrieben (Vite-Build aus php-fpm).

## Wann welcher Rebuild

| Änderung | Rebuild | Befehl |
|---|---|---|
| `app/`, `resources/views/`, `routes/`, Composer | `php-fpm` | `docker compose build php-fpm && docker compose up -d php-fpm` |
| `public/css`, `public/js`, `public/fonts`, `public/images` | **keiner** | Datei direkt ändern, sofort live |
| Filament-Assets fehlen | `php-fpm` exec | `docker compose exec php-fpm php artisan filament:assets` |
| `docker/common/nginx/` Konfiguration | `web` | `docker compose build web && docker compose up -d web` |
| View-Cache leeren | php-cli run | `docker compose run --rm php-cli php artisan view:clear --no-ansi` |
| Tests (außerhalb `tests/`, `phpunit.xml`, `.env`) | `php-test` | `docker compose build php-test` |

## Standard-Workflow nach Code-Änderungen

```bash
# PHP-Code geändert:
docker compose build php-fpm 2>&1 | tail -5
docker compose up -d php-fpm
docker compose run --rm php-cli php artisan view:clear --no-ansi

# Nur Views/Blade geändert (kein PHP):
docker compose run --rm php-cli php artisan view:clear --no-ansi

# Filament-Assets neu publizieren:
docker compose exec php-fpm php artisan filament:assets
# (landen direkt in ./public/css,js,fonts/ — sofort via Bind-Mount sichtbar)
```

## Volumes

| Volume | Inhalt | Verwendung |
|---|---|---|
| `linn-storage-production` | `storage/` | php-fpm (rw), web (ro), queue-worker (rw) |
| `linn-build-assets` | `public/build/` (Vite) | php-fpm schreibt → web liest |

## Lokale Tests

```bash
docker compose run --rm php-test vendor/bin/pest
```

Nach Code-Änderungen außerhalb von `tests/`, `phpunit.xml`, `.env`:
```bash
docker compose build php-test && docker compose run --rm php-test vendor/bin/pest
```

## Sicherheitsregeln für docker-compose.yml

- `./public` in `web` immer mit `:ro` mounten — nginx schreibt nie in public
- `public/build/` muss auf dem Host existieren (nicht in `.gitignore` aber via `npm run build` vorhanden), damit Docker den Mountpoint für das named Volume erstellen kann
- Secrets nur via `.env` + `env_file` — niemals hardcoded in docker-compose.yml
- In Production: `.env` enthält `APP_ENV=production`, `APP_DEBUG=false`

## Dev vs. Production

Beide nutzen dieselbe `docker-compose.yml`. Unterschiede steuert `.env`:

| Variable | Dev | Production |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://localhost:6480` | `https://app.linn.games` |
| `APP_PORT` | `6480` | `6481` |
| `LOG_LEVEL` | `debug` | `warning` |

Production-Deployment erfolgt manuell via `./deploy.sh` auf dem Synology NAS.
