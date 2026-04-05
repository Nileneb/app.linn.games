---
description: "Lokale Entwicklungsumgebung für app.linn.games einrichten und arbeiten"
applyTo: "**/*"
---

# Local Development Setup — app.linn.games

## 🚀 Schnelleinstieg (4 Schritte)

### 1. **Repository klonen & Dependencies installieren**

```bash
git clone https://github.com/Nileneb/app.linn.games.git
cd app.linn.games
composer setup
```

**Was passiert:**
- `.env` Datei wird aus `.env.example` kopiert
- App-Key wird generiert
- Datenbank wird migriert
- Rollen & Test-User werden mit `db:seed` erstellt
- npm Dependencies werden installiert & gebaut

### 2. **Docker-Container starten**

```bash
docker compose up -d
```

**Container:**
- `php-fpm` — Laravel App (Port 6480)
- `nginx` — Web Server
- `postgres` — Datenbank (PostgreSQL 16)
- `redis` — Cache/Queue

Verifizierung:
```bash
docker compose ps
```

### 3. **Browser öffnen**

```
http://localhost:6480
```

### 4. **Mit Test-User einloggen**

Nach `composer setup` sind automatisch folgende Test-User verfügbar:

| Email | Passwort | Rolle | Zweck |
|---|---|---|---|
| `editor@test.local` | `password` | Editor | Vollständiger Zugriff auf Recherche-Features |
| `member@test.local` | `password` | Member | Standard Mitglied (Read/Write) |
| `admin-test@test.local` | `password` | Admin | Admin-Panel + System-Verwaltung |
| `admin@example.com` | reset-link | Admin | Admin der Demo-Workspace |

---

## ⚙️ Entwicklung starten

### **Laravel Dev-Server** (optional, falls nicht Docker)

```bash
php artisan serve
```

### **Frontend Vite Dev-Server** (optional, für CSS/JS HotReload)

```bash
npm run dev
```

### **Queue Worker** (für asynchrone Jobs)

```bash
php artisan queue:work --timeout=180
```

### **Migrationen testen**

```bash
php artisan migrate:refresh --seed
```

---

## 🧪 Tests ausführen

```bash
# Alle Tests
composer test

# Spezifische Test-Datei
composer test tests/Feature/RechercheTest.php

# Mit Coverage
composer test -- --coverage
```

**Hinweis:** Tests laufen gegen PostgreSQL (wie Production). MySQL wird NICHT unterstützt.

---

## 📁 Project-Setup Übersicht

```
.env                           # Umgebungsvariablen (gitignoriert)
docker-compose.yml             # Docker-Setup für dev
docker-compose.override.yml    # Dev-spezifische Overrides
composer.json                  # PHP + setup-Script
package.json                   # npm + Vite-Build-Script

app/                           # Laravel App-Code
├── Models/                    # Eloquent Models
├── Jobs/                      # Queued Jobs
├── Services/                  # Business Logic
└── Livewire/                  # Volt Components

resources/
├── views/livewire/            # Volt (inline) Components
├── css/                       # Tailwind CSS
└── js/                        # Vite JS

database/
├── migrations/                # Datenbankmigrationen
├── factories/                 # Eloquent Factories
└── seeders/                   # Datenbank-Seeder
    ├── DatabaseSeeder.php     # Haupteinstieg
    ├── RoleSeeder.php         # Rollen & Permissions
    ├── DevSeeder.php          # Test-User für Dev
    └── RechercheDemoSeeder.php# Demo-Projekt mit Daten

tests/
├── Feature/                   # Integration Tests
├── Unit/                      # Unit Tests
└── Pest.php                   # Pest-Config
```

---

## 🔧 Common Development Tasks

### **Datenbank zurücksetzen**

```bash
php artisan migrate:refresh --seed
```

_Warnung: Löscht alle Daten!_

### **Nur Seed ausführen** (ohne Migration)

```bash
php artisan db:seed
```

### **Spezifischen Seeder ausführen**

```bash
# Nur Test-User erstellen
php artisan db:seed --class=DevSeeder

# Demo-Projekt mit Recherche-Daten
php artisan db:seed --class=RechercheDemoSeeder

# Alles resetten + alle Seeder
php artisan migrate:refresh --seed
```

### **Neue Migration erstellen**

```bash
php artisan make:migration create_meine_tabelle
```

### **Neue Volt-Komponente erstellen**

```bash
php artisan make:volt recherche.meine-komponente
```

### **Lint & Code-Formatting**

```bash
# PHP Linting (PHPStan)
./vendor/bin/phpstan analyze

# Code formatieren (Pint)
./vendor/bin/pint
```

---

## 🐛 Debugging

### **Datenbank-Zugriff**

```bash
# PostgreSQL CLI in Docker
docker compose exec postgres psql -U app_linn_games -d app_linn_games

# Oder mit pgAdmin GUI (falls verfügbar)
# http://localhost:5050
```

### **Logs anschauen**

```bash
# Laravel Logs
tail -f storage/logs/laravel.log

# Queue Worker Logs
php artisan queue:work --verbose
```

### **Redis inspizieren**

```bash
# Redis CLI
docker compose exec redis redis-cli
> KEYS *
> GET key-name
```

### **Livewire Debug**

In Browser Console:
```javascript
Livewire.showWarnings(); // oder .hideWarnings()
```

---

## 📝 .env Wichtige Variablen (für Dev)

```env
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=app_linn_games
DB_USERNAME=app_user
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis

# Mailables (in Dev meist zu Logs)
MAIL_MAILER=log

# Langdock Agent API (optional für lokale Entwicklung)
LANGDOCK_API_KEY=test-key
LANGDOCK_AGENT_ID=test-id

# Ollama für Embeddings (lokal)
OLLAMA_URL=http://localhost:11434
```

---

## ❌ Häufige Probleme

### **"SQLSTATE[HY000]: General error: 7 ERROR: type "xxx" does not exist"**

PostgreSQL Custom Types fehlen. Lösung:
```bash
php artisan migrate:refresh --seed
```

### **Port 6480/6481 bereits in Benutzung**

In `docker-compose.override.yml` ändern:
```yaml
services:
  nginx:
    ports:
      - "6482:80"  # statt 6480
```

### **"Column not found in database"**

Eine Migration wurde nicht ausgeführt:
```bash
php artisan migrate
```

### **npm build schlägt fehl**

Vite-Cache löschen:
```bash
rm -rf node_modules/.vite
npm run build
```

### **Tests schlagen fehl mit "Driver not supported"**

Sicherstellen, dass PostgreSQL Connection in `phpunit.xml` konfiguriert ist:
```xml
<env name="DB_CONNECTION" value="pgsql"/>
```

---

## 🚀 Deployment (Production)

Siehe [./deploy.sh](../../deploy.sh) und `.github/instructions/docker-dev-workflow.instructions.md`

---

## 📞 Hilfe & Kontakt

- **Issues:** [GitHub Issues](https://github.com/Nileneb/app.linn.games/issues)
- **Codebase:** Siehe `.github/instructions/app_linn_games_custom.instructions.md`
- **Docker Setup:** Siehe `.github/instructions/docker-dev-workflow.instructions.md`
