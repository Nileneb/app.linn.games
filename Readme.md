# 🚀 LINN.GAMES LARAVEL APP - MASTER TODO

## 📋 Projektübersicht
**Scope:** Laravel-Monorepo für www.linn.games + mau.linn.games  
**Ausgeklammert:** grow.linn.games (separate Laravel 12 App)  
**Status:** Planning Phase  
**Architektur:** Multi-Domain Laravel mit Subdomain-Routing

---

## ✅ PHASE 1: Laravel-Projekt Setup & Architektur

### 1.1 Projekt-Initialisierung
- [ ] Laravel 11 installieren (`composer create-project laravel/laravel linn-games`)
- [ ] `.env` konfigurieren (DB, APP_URL, MAIL, etc.)
- [ ] PostgreSQL Datenbank erstellen: `linn_games_production`
- [ ] Git-Repository initialisieren + `.gitignore` anpassen

### 1.2 Core Packages installieren
- [ ] `spatie/laravel-permission` (Roles & Permissions)
- [ ] `spatie/laravel-cookie-consent` (DSGVO Cookie-Banner)
- [ ] `laravel/sanctum` (API Authentication)
- [ ] `filament/filament` (Admin Panel) ODER `laravel/nova`
- [ ] `laravel/horizon` (Queue Monitoring - optional)
- [ ] `spatie/laravel-activitylog` (Audit Trail)

### 1.3 Multi-Domain Routing Setup
- [ ] Middleware: `SubdomainRouter.php` erstellen
- [ ] Route-Gruppen in `routes/web.php`:
  - `www.linn.games` → Main Website
  - `mau.linn.games` → MAU Campaign
- [ ] `.env` Variablen: `APP_DOMAIN=linn.games`
- [ ] Vhost/Nginx Config für Wildcard-Domain `*.linn.games`

### 1.4 Frontend-Stack Entscheidung
- [ ] **Option A:** Laravel Blade + Livewire 3 (für Admin)
- [ ] **Option B:** Inertia.js + Vue/React (für Main Site API)
- [ ] **Empfehlung:** Blade für Admin, API für Frontend (React bleibt)
- [ ] Tailwind CSS Installation
- [ ] Vite Config anpassen

---

## 🗄️ PHASE 2: Models & Datenbank-Schema

### 2.1 Core Authentication Models

#### User Model (erweitern)
- [ ] Migration: Add fields
  ```php
  - unity_id (nullable, unique)
  - github_id (nullable, unique)
  - avatar_url
  - is_admin (boolean)
  - email_verified_at
  - last_login_at
  ```
- [ ] Model: Traits hinzufügen
  - `HasRoles` (Spatie Permission)
  - `LogsActivity` (Spatie Activity Log)
- [ ] Seeder: Admin-User erstellen

#### Consent Model
- [ ] Migration: `consents` Table
  ```php
  - id
  - user_id (nullable, for logged-in users)
  - ip_address
  - user_agent
  - consent_technical (boolean, always true)
  - consent_analytics (boolean)
  - consent_marketing (boolean)
  - given_at (timestamp)
  - expires_at (timestamp)
  ```
- [ ] Model: `Consent.php`
- [ ] Relation: `User hasMany Consent`

---

### 2.2 WWW.LINN.GAMES Models

#### ContactInquiry Model
- [ ] Migration: `contact_inquiries` Table
  ```php
  - id
  - name
  - company (nullable)
  - email
  - project_type (enum: game, ai, web, other)
  - message (text)
  - preferred_contact_date (nullable)
  - status (enum: new, in_progress, replied, closed)
  - ip_address
  - user_agent
  - created_at, updated_at
  ```
- [ ] Model: `ContactInquiry.php`
  - Validation Rules
  - Observers für Email-Notification
- [ ] Controller: `ContactController@store`
- [ ] **Rate Limiting:** 3 Anfragen/Stunde/IP

#### Project Model
- [ ] Migration: `projects` Table
  ```php
  - id
  - slug (unique)
  - title
  - short_description
  - full_description (text)
  - technologies (json) // ["Unity", "WebGPU", "C#"]
  - category (enum: game, iot, ai, web)
  - featured_image
  - demo_url (nullable)
  - github_url (nullable)
  - is_published (boolean)
  - published_at (timestamp)
  - view_count (integer)
  - created_at, updated_at
  ```
- [ ] Model: `Project.php`
  - Sluggable Trait
  - Scopes: `published()`, `byCategory()`
- [ ] Seeder: Bestehende Projekte (Snake3D, GrowDash, MAU)

#### Service Model
- [ ] Migration: `services` Table
  ```php
  - id
  - slug (unique)
  - title
  - icon (string, e.g., "game-controller")
  - short_description
  - full_description (text)
  - category (enum: games, ai, web)
  - order (integer, for sorting)
  - is_active (boolean)
  ```
- [ ] Model: `Service.php`
- [ ] Seeder: 3 Core Services (Interactive Games, AI Solutions, Web Development)

#### BlogPost Model (Optional - für zukünftigen Content)
- [ ] Migration: `blog_posts` Table
  ```php
  - id
  - slug (unique)
  - title
  - excerpt
  - content (text)
  - featured_image
  - author_id (FK to users)
  - published_at (nullable)
  - seo_title, seo_description
  - created_at, updated_at
  ```
- [ ] Model: `BlogPost.php`
- [ ] Relation: `User hasMany BlogPost`

---

### 2.3 MAU.LINN.GAMES Models

#### MauScan Model
- [ ] Migration: `mau_scans` Table
  ```php
  - id
  - qr_code_id (string, e.g., "qr_001_backpack")
  - scanned_at (timestamp)
  - location_lat (nullable)
  - location_lng (nullable)
  - ip_address
  - user_agent
  - referrer (nullable)
  ```
- [ ] Model: `MauScan.php`
- [ ] Analytics: Aggregierte Stats für Admin-Dashboard

#### MauMemoryScore Model
- [ ] Migration: `mau_memory_scores` Table
  ```php
  - id
  - player_name
  - score (integer)
  - moves (integer)
  - time_seconds (integer)
  - user_id (nullable, FK if logged in)
  - ip_address
  - created_at
  ```
- [ ] Model: `MauMemoryScore.php`
- [ ] Relation: `User hasMany MauMemoryScore`
- [ ] Leaderboard Query: Top 10 Scores

#### InstagramPost Model (Optional)
- [ ] Migration: `instagram_posts` Table
  ```php
  - id
  - instagram_id (unique)
  - username
  - post_url
  - image_url
  - caption (text, nullable)
  - tagged_at (timestamp)
  ```
- [ ] Model: `InstagramPost.php`
- [ ] **Instagram API Integration** (später, optional)

---

### 2.4 Pivot Tables & Relations

#### Many-to-Many: Projects <-> Services (Optional)
- [ ] Migration: `project_service` Pivot Table
  ```php
  - project_id
  - service_id
  ```
- [ ] Relation in `Project.php`: `belongsToMany(Service::class)`

---

## 🛣️ PHASE 3: Routes & Controller Implementation

### 3.1 WWW.LINN.GAMES Routes

```php
// routes/web.php - Main Domain
Route::domain('www.linn.games')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('project.show');
    
    // Contact Form (Rate Limited)
    Route::post('/contact', [ContactController::class, 'store'])
         ->middleware('throttle:3,60') // 3 per hour
         ->name('contact.store');
});

// API Routes for React Frontend
Route::prefix('api')->group(function () {
    Route::get('/projects', [Api\ProjectController::class, 'index']);
    Route::get('/services', [Api\ServiceController::class, 'index']);
});
```

- [ ] `HomeController.php` erstellen
- [ ] `ProjectController.php` erstellen
- [ ] `ContactController.php` erstellen + Email-Notification
- [ ] API Controllers für React-Frontend

### 3.2 MAU.LINN.GAMES Routes

```php
// routes/web.php - MAU Subdomain
Route::domain('mau.linn.games')->group(function () {
    Route::get('/', [MauHomeController::class, 'index'])->name('mau.home');
    
    // Memory Game API
    Route::post('/memory/score', [MauMemoryController::class, 'store'])
         ->name('mau.memory.score');
    
    // QR Scan Tracking
    Route::post('/scan', [MauScanController::class, 'track'])
         ->name('mau.scan.track');
    
    // Stats (for display)
    Route::get('/stats', [MauScanController::class, 'stats'])
         ->name('mau.stats');
});
```

- [ ] `MauHomeController.php` erstellen
- [ ] `MauMemoryController.php` erstellen
- [ ] `MauScanController.php` erstellen

### 3.3 Admin Routes (Filament)

- [ ] Install Filament: `composer require filament/filament`
- [ ] `php artisan filament:install --panels`
- [ ] Admin-Panel URL: `www.linn.games/admin`
- [ ] Filament Resources erstellen:
  - [ ] `ContactInquiryResource`
  - [ ] `ProjectResource`
  - [ ] `ServiceResource`
  - [ ] `MauMemoryScoreResource` (Read-only)
  - [ ] `UserResource`

---

## 🔐 PHASE 4: Consent & DSGVO-Compliance

### 4.1 Cookie-Consent Implementation

- [ ] Package: `composer require spatie/laravel-cookie-consent`
- [ ] Publish config: `php artisan vendor:publish --tag=cookie-consent-config`
- [ ] Blade Component erstellen: `<x-cookie-consent-banner />`
- [ ] Consent-Settings Modal/Page:
  - ✅ Technisch notwendig (immer aktiv)
  - ⚠️ Analytics (opt-in) - AKTUELL NICHT GENUTZT
  - ⚠️ Marketing (opt-in) - AKTUELL NICHT GENUTZT

### 4.2 Consent-Tracking

- [ ] Middleware: `TrackConsent.php`
  - Speichert Consent-Einstellungen in `consents` Table
  - Cookie: `linn_consent` (12 Monate Laufzeit)
- [ ] Route: `POST /consent` → `ConsentController@store`
- [ ] Footer-Link: "Cookie-Einstellungen ändern"

### 4.3 DSGVO-Auskunft-Tool

- [ ] Controller: `DataExportController.php`
- [ ] Route: `GET /dsgvo/export` (authenticated)
- [ ] Export-Format: JSON + PDF
- [ ] Daten-Kategorien:
  - User Account Data
  - Contact Inquiries
  - MAU Memory Scores
  - Consents
- [ ] Löschung: `DELETE /dsgvo/delete-account`

---

## 📄 PHASE 5: AGB & Datenschutz-Update

### 5.1 Rechtliche Dokumente in DB migrieren

- [ ] Migration: `legal_documents` Table
  ```php
  - id
  - type (enum: impressum, dsgvo, agb)
  - version
  - content (longText)
  - effective_from (date)
  - is_current (boolean)
  ```
- [ ] Seeder: Bestehende HTML-Inhalte importieren
- [ ] Route: `GET /{impressum|dsgvo|agb}` → `LegalController@show`

### 5.2 AGB-Aktualisierungen

**Neue Abschnitte hinzufügen:**
- [ ] § Kontaktformular-Daten
  - Zweck: Bearbeitung von Anfragen
  - Speicherdauer: 24 Monate
  - Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO
- [ ] § Cookie-Consent-Management
  - Beschreibung der Consent-Kategorien
  - Widerrufsmöglichkeit
- [ ] § API-Nutzung (für Frontend-Integration)

### 5.3 Datenschutzerklärung erweitern

- [ ] **Hosting-Provider** dokumentieren:
  - Synology NAS (selbst gehostet?)
  - Oder: Hetzner / AWS / DigitalOcean?
- [ ] **Sub-Processors** auflisten:
  - Laravel Forge (falls genutzt)
  - Email-Provider (Mailgun / SES / SMTP?)
  - Unity OAuth
- [ ] **Cookie-Liste** aktualisieren:
  - Session-Cookie: `laravel_session` (technisch notwendig)
  - Consent-Cookie: `linn_consent` (12 Monate)
  - CSRF-Token: `XSRF-TOKEN` (technisch notwendig)

### 5.4 Impressum

- [ ] Prüfen: USt-ID notwendig?
  - Falls digitale Verkäufe > €17.500/Jahr → Ja
  - Sonst: Kleinunternehmer-Regelung
- [ ] Streitschlichtung: Link zu EU-Plattform hinzufügen
  - https://ec.europa.eu/consumers/odr

---

## 🎨 PHASE 6: Frontend-Integration

### 6.1 API-Endpoints für React-Frontend

- [ ] `GET /api/projects` → ProjectResource (JSON)
- [ ] `GET /api/services` → ServiceResource (JSON)
- [ ] `POST /api/contact` → ContactController
  - Validation: Name, Email, Message
  - Rate Limiting: 3/hour/IP
  - Response: `{"success": true, "message": "..."}`

### 6.2 CORS-Konfiguration

- [ ] `config/cors.php` anpassen
  ```php
  'paths' => ['api/*'],
  'allowed_origins' => ['https://www.linn.games'],
  ```

### 6.3 Contact-Form Backend-Logic

- [ ] Validation Rules:
  ```php
  'name' => 'required|string|max:100',
  'email' => 'required|email|max:255',
  'company' => 'nullable|string|max:100',
  'project_type' => 'required|in:game,ai,web,other',
  'message' => 'required|string|max:2000',
  ```
- [ ] Email-Notification (Queue):
  - An: `info@linn.games`
  - Subject: "Neue Kontaktanfrage von {name}"
  - Template: `emails.contact-inquiry`
- [ ] Auto-Response an User:
  - Subject: "Ihre Anfrage bei Linn Games"
  - Confirmation-Mail

---

## 🔧 PHASE 7: Technische Features

### 7.1 Queue-System Setup

- [ ] `.env`: `QUEUE_CONNECTION=redis` (oder `database`)
- [ ] Install Redis: `composer require predis/predis`
- [ ] Create Jobs:
  - [ ] `SendContactInquiryEmail.php`
  - [ ] `SendContactConfirmationEmail.php`
- [ ] Queue-Worker starten: `php artisan queue:work`
- [ ] Supervisor Config (für Production)

### 7.2 Caching-Strategy

- [ ] Cache-Driver: Redis
- [ ] Cache-Keys:
  - `projects:published` (60 min)
  - `services:active` (120 min)
  - `mau:stats:daily` (24h)
- [ ] Cache-Invalidierung:
  - Observer: Bei Project/Service-Update → Cache::forget()

### 7.3 Rate Limiting

- [ ] Route-Middleware:
  ```php
  Route::post('/contact')->middleware('throttle:3,60'); // 3/hour
  Route::post('/mau/memory/score')->middleware('throttle:10,1'); // 10/min
  ```
- [ ] Custom Throttle für IP-basiert:
  ```php
  RateLimiter::for('contact-form', function (Request $request) {
      return Limit::perHour(3)->by($request->ip());
  });
  ```

### 7.4 Logging & Monitoring

- [ ] Laravel Telescope (Development):
  - `composer require laravel/telescope --dev`
  - `php artisan telescope:install`
  - URL: `/telescope` (nur lokal/staging)
- [ ] Sentry Integration (Production):
  - `composer require sentry/sentry-laravel`
  - `.env`: `SENTRY_LARAVEL_DSN=...`
- [ ] Custom Log-Channels:
  ```php
  // config/logging.php
  'mau' => [
      'driver' => 'daily',
      'path' => storage_path('logs/mau.log'),
  ],
  ```

---

## 🚀 PHASE 8: Deployment & DevOps

### 8.1 Server-Setup

- [ ] **Hosting-Entscheidung:**
  - Option A: Synology NAS (wie grow.linn.games)
  - Option B: Hetzner Cloud VPS
  - Option C: Laravel Forge + DigitalOcean
- [ ] Nginx/Apache Config:
  - Wildcard-Domain: `*.linn.games` → `/public`
  - SSL: Let's Encrypt Wildcard-Cert
- [ ] PHP 8.2+ Installation
- [ ] PostgreSQL Setup
- [ ] Redis Installation

### 8.2 SSL-Zertifikate

- [ ] Wildcard-Cert beantragen:
  ```bash
  certbot certonly --manual --preferred-challenges dns     -d *.linn.games -d linn.games
  ```
- [ ] DNS TXT-Record setzen (für Validierung)
- [ ] Auto-Renewal Cronjob:
  ```bash
  0 3 * * * certbot renew --quiet
  ```

### 8.3 Deployment-Pipeline (GitHub Actions)

- [ ] `.github/workflows/deploy.yml` erstellen:
  ```yaml
  name: Deploy to Production
  on:
    push:
      branches: [main]
  jobs:
    deploy:
      runs-on: ubuntu-latest
      steps:
        - name: Checkout code
        - name: Run tests
        - name: Deploy via SSH
        - name: Run migrations
        - name: Clear cache
  ```
- [ ] Secrets konfigurieren: `SSH_PRIVATE_KEY`, `DB_PASSWORD`, etc.

### 8.4 Backup-Strategy

- [ ] **Database Backups:**
  - Cronjob: `0 2 * * * pg_dump linn_games > backup.sql`
  - Retention: 30 Tage
  - Off-site: S3 / Dropbox
- [ ] **Code Backups:**
  - Git-Repository (GitHub/GitLab)
- [ ] **Media-Backups:**
  - `/storage/app/public/uploads` → rsync zu Backup-Server

---

## 📊 PHASE 9: Analytics & Tracking (Optional)

### 9.1 Privacy-First Analytics

**WICHTIG:** Aktuell KEINE Analytics aktiv!

**Option A: Plausible Analytics (Empfohlen)**
- [ ] Account erstellen: https://plausible.io
- [ ] Script einbinden (DSGVO-konform, kein Cookie-Consent nötig)
- [ ] Events tracken:
  - `Contact Form Submit`
  - `Project View`
  - `QR Code Scan`

**Option B: Matomo (Self-Hosted)**
- [ ] Matomo installieren auf Subdomain: `analytics.linn.games`
- [ ] Cookie-less Tracking aktivieren
- [ ] IP-Anonymisierung: 2 Bytes

### 9.2 Conversion-Tracking

- [ ] Contact-Form Submissions (bereits in DB)
- [ ] Project-Views (Counter in `projects.view_count`)
- [ ] QR-Code Scans (in `mau_scans` Table)
- [ ] Dashboard-Widget in Filament Admin

---

## 🎯 PHASE 10: Testing & QA

### 10.1 Unit Tests

- [ ] PHPUnit Setup: `php artisan test`
- [ ] Feature Tests:
  - [ ] `ContactFormTest.php` (Rate Limiting, Validation)
  - [ ] `MauMemoryScoreTest.php` (Leaderboard)
  - [ ] `ProjectTest.php` (Slug-Generation)

### 10.2 Browser Tests (Laravel Dusk)

- [ ] Install Dusk: `composer require laravel/dusk --dev`
- [ ] Tests:
  - [ ] Contact-Form Submission
  - [ ] Cookie-Consent Banner
  - [ ] MAU Memory Game

### 10.3 Security Audit

- [ ] OWASP Top 10 Check:
  - [x] SQL Injection (Eloquent ORM schützt)
  - [x] XSS (Blade `{{ }}` escaped)
  - [ ] CSRF (Token-Validierung aktiv?)
  - [ ] Rate Limiting (implementiert)
- [ ] Dependency Audit: `composer audit`

---

## ✨ PHASE 11: Nice-to-Have Features

### 11.1 Newsletter-System

- [ ] Model: `Newsletter` (email, subscribed_at)
- [ ] Form auf Website
- [ ] Mailchimp/Sendinblue Integration

### 11.2 Multi-Language Support

- [ ] Package: `spatie/laravel-translatable`
- [ ] Sprachen: DE (default), EN
- [ ] Models mit Übersetzungen: `Project`, `Service`

### 11.3 SEO-Optimierungen

- [ ] Sitemap-Generator: `/sitemap.xml`
- [ ] Meta-Tags in Blade-Templates
- [ ] Open Graph Tags für Social Sharing
- [ ] JSON-LD Schema.org Markup

---

## 📌 PRIORITÄTEN & TIMELINE

### 🔥 SPRINT 1 (Woche 1-2): Foundation
- Phase 1: Laravel Setup
- Phase 2: Core Models (User, ContactInquiry, Project)
- Phase 3: Main Routes & Contact-Form

### ⚡ SPRINT 2 (Woche 3): MAU Integration
- Phase 2.3: MAU Models
- Phase 3.2: MAU Routes
- Phase 4: Consent-Management (Basis)

### 🎨 SPRINT 3 (Woche 4): Admin & Legal
- Phase 3.3: Filament Admin
- Phase 5: AGB/DSGVO Update
- Phase 6: API für React-Frontend

### 🚀 SPRINT 4 (Woche 5-6): Production-Ready
- Phase 7: Queue, Cache, Logging
- Phase 8: Deployment
- Phase 10: Testing

---

## 🛠️ TOOLS & STACK

### Backend
- **Framework:** Laravel 11
- **Database:** PostgreSQL 15+
- **Cache/Queue:** Redis
- **Admin:** Filament v3

### Frontend
- **Main Site:** React (bestehend, via API)
- **MAU Site:** Blade + Alpine.js (oder React)
- **CSS:** Tailwind CSS

### DevOps
- **Hosting:** TBD (Synology / Hetzner / Forge)
- **CI/CD:** GitHub Actions
- **Monitoring:** Sentry + Laravel Telescope

---

## 📞 NÄCHSTE SCHRITTE

1. ✅ Architektur-Entscheidung: Monorepo bestätigt
2. ⏳ Laravel-Projekt aufsetzen (PHASE 1)
3. ⏳ Datenbank-Schema finalisieren (PHASE 2)
4. ⏳ Contact-Form Backend implementieren (PHASE 3.1)

**Start-Kommando:**
```bash
composer create-project laravel/laravel linn-games "11.*"
cd linn-games
cp .env.example .env
php artisan key:generate
```

---

**Last Updated:** $(date)
**Version:** 1.0
**Author:** Linn Games Development Team
