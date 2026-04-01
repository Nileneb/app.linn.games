---
applyTo: "docker-compose.yml,docker-compose.override.yml,docker/**,Dockerfile*"
---

# Docker Dev-Workflow — app.linn.games

## Architektur

10 Container: `web`, `php-fpm`, `php-cli`, `php-test`, `queue-worker`, `postgres`, `redis`, `postgres-mcp`, `ollama`, `mcp-paper-search`

**Code-Deployment (Production)**: PHP-Code wird beim Docker-Build via `COPY . /var/www` in die Images gebaked (`php-fpm`, `php-cli`, `queue-worker`).

**Code-Deployment (Dev)**: `docker-compose.override.yml` mounted `app/`, `resources/`, `config/`, `routes/`, `database/`, `bootstrap/` als Bind-Mounts in die Container. Code-Änderungen an PHP/Blade/Config sind **sofort** wirksam — kein Rebuild nötig. Zusätzlich überschreibt `php-dev.ini` die OPcache-Einstellungen, sodass PHP Dateiänderungen bei jedem Request erkennt.

**Static Assets**: `./public` ist als `:ro` Bind-Mount in `web` (nginx) eingebunden. Änderungen an `public/` (CSS, JS, Filament-Assets) sind sofort wirksam — **kein nginx-Rebuild nötig**.

Ausnahme: `public/build` wird zusätzlich vom named Volume `linn-build-assets` überschrieben (Vite-Build aus php-fpm).

## Dev-Setup (einmalig)

Falls `docker-compose.override.yml` nicht existiert (z.B. nach `git clone`):

```bash
# Override existiert bereits im Repo-Root — wird nur nicht committed (.gitignore)
# Bei Ersteinrichtung: Datei manuell erstellen oder vom Beispiel kopieren
```

Die Override-Datei mounted Source-Code als Bind-Mounts und aktiviert `php-dev.ini` (OPcache Timestamp-Validierung + Error-Display). Docker Compose merged `docker-compose.yml` + `docker-compose.override.yml` automatisch.

## Wann welcher Rebuild

### Mit docker-compose.override.yml (Dev — Standard)

| Änderung | Rebuild | Befehl |
|---|---|---|
| `app/`, `resources/views/`, `routes/`, `config/` | **keiner** | Datei ändern → Browser reload |
| `composer.json` / `composer.lock` (neue Packages) | `php-fpm` | `docker compose build php-fpm && docker compose up -d php-fpm` |
| `package.json` / Vite-Config (Frontend-Build) | `php-fpm` | `docker compose build php-fpm && docker compose up -d php-fpm` |
| `public/css`, `public/js`, `public/fonts`, `public/images` | **keiner** | Datei direkt ändern, sofort live |
| Filament-Assets fehlen | `php-fpm` exec | `docker compose exec php-fpm php artisan filament:assets` |
| `docker/common/nginx/` Konfiguration | `web` | `docker compose build web && docker compose up -d web` |
| View-Cache leeren | php-fpm exec | `docker compose exec php-fpm php artisan view:clear` |
| Tests (außerhalb `tests/`, `phpunit.xml`, `.env`) | `php-test` | `docker compose build php-test` |

### Ohne docker-compose.override.yml (Production)

| Änderung | Rebuild | Befehl |
|---|---|---|
| Jede Code-Änderung | `php-fpm` | `docker compose -f docker-compose.yml build php-fpm && docker compose -f docker-compose.yml up -d php-fpm` |

## Standard-Workflow nach Code-Änderungen (Dev)

```bash
# PHP/Blade/Config geändert:
# → Nichts tun. Browser reloaden. Fertig.

# Kompilierte View-Cache stört (z.B. nach Layout-Umbau):
docker compose exec php-fpm php artisan view:clear

# Neue Composer-Packages:
docker compose build php-fpm && docker compose up -d php-fpm

# Filament-Assets neu publizieren:
docker compose exec php-fpm php artisan filament:assets
```

## Volumes

| Volume | Inhalt | Verwendung |
|---|---|---|
| `linn-storage-production` | `storage/` | php-fpm (rw), web (ro), queue-worker (rw) |
| `linn-build-assets` | `public/build/` (Vite) | php-fpm schreibt → web liest |

## Bind-Mounts (Dev via Override)

| Host-Pfad | Container-Pfad | Modus | Zweck |
|---|---|---|---|
| `./app` | `/var/www/app` | `:ro` | PHP-Code live |
| `./resources` | `/var/www/resources` | `:ro` | Blade/Views live |
| `./config` | `/var/www/config` | `:ro` | Config live |
| `./routes` | `/var/www/routes` | `:ro` | Routes live |
| `./database` | `/var/www/database` | rw | Migrations (write nötig) |
| `./bootstrap` | `/var/www/bootstrap` | rw | Bootstrap-Cache (write nötig) |
| `php-dev.ini` | `zz-dev.ini` | `:ro` | OPcache Dev-Overrides |

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
- `docker-compose.override.yml` wird NICHT committed — in `.gitignore` eingetragen
- Production-Deploy: `docker compose -f docker-compose.yml up -d` (Override ignoriert)

## Dev vs. Production

Beide nutzen dieselbe `docker-compose.yml`. Unterschiede steuert `.env` + Optional Override:

| Variable | Dev | Production |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://localhost:6480` | `https://app.linn.games` |
| `APP_PORT` | `6480` | `6481` |
| `LOG_LEVEL` | `debug` | `warning` |
| Override-Datei | `docker-compose.override.yml` vorhanden | nicht vorhanden |
| Code-Mount | Bind-Mounts (live) | gebakt im Image |
| OPcache | Timestamp-Check bei jedem Request | Production-Defaults |

Production-Deployment erfolgt manuell via `./deploy.sh` auf dem Synology NAS.
