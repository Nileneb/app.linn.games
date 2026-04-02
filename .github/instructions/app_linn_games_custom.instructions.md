---
description: Startpunkt für die Dokumentation der Architektur, Konventionen und kritischen Details von app.linn.games. Alle Entwickler und KI-Agenten müssen diese Datei verstehen, um effektiv am Projekt arbeiten zu können.
applyTo: '**/*'
---
# Architektur- und Entwicklungsgrundlagen — app.linn.games
---
# INSTRUCTIONS.md — app.linn.games

> Diese Datei ist die verbindliche Arbeitsgrundlage für alle Code-Agenten und Entwickler.
> Sie beschreibt Architektur, Konventionen, Fallstricke und Entscheidungen, die ohne Einarbeitung verstanden werden müssen.

---

## 1. Projektübersicht

**app.linn.games** ist eine proprietäre Research-Management-Plattform für KI-gestützte systematische Literaturrecherche.

- Nutzer erstellen Recherche-Projekte, geben eine Forschungsfrage ein und durchlaufen 8 definierte Phasen (P1–P8)
- Ein KI-Agent (Langdock) wird asynchron über eine Queue ausgelöst und arbeitet mit der Datenbank via MCP-Protokoll
- Fachartikel werden heruntergeladen, in Chunks aufgeteilt, via Ollama als Vektoren eingebettet und in pgvector gespeichert

---

## 2. Stack (exakte Versionen)

| Komponente | Version | Hinweis |
|---|---|---|
| PHP | 8.2+ (CI: 8.4) | Strikt typisiert |
| Laravel | 12 | Kein Legacy-Code |
| Livewire / Volt | 3 / 1.7 | Inline-Komponenten |
| Filament | 4.9 | Schema-basiert |
| Fortify | 1.30 | Auth + 2FA, plain Blade |
| Tailwind CSS | 4 | via Vite |
| PostgreSQL | 16 | UUID-PKs, pgvector, Custom Enums |
| Redis | Alpine | Cache, Session, Queue |
| Ollama | lokal | `nomic-embed-text` Embedding-Modell |
| Langdock | extern | KI-Agent via Webhook + MCP |

---

## 3. Verzeichnisstruktur (nur das Wesentliche)

```
app/
├── Filament/Resources/        # Admin-Panel: ContactResource, UserResource
├── Http/
│   ├── Controllers/
│   │   ├── ContactController.php        # Kontaktformular
│   │   ├── DsgvoController.php          # DSGVO-Export & Account-Löschung
│   │   ├── LangdockWebhookController.php # Eingehende Langdock-Webhooks
│   │   └── PaperRagController.php       # Paper ingest + Vektorsuche
│   └── Middleware/
│       ├── VerifyLangdockSignature.php  # HMAC + Timestamp + Nonce-Replay-Schutz
│       └── VerifyMcpToken.php           # Bearer-Token-Auth für /mcp/sse
├── Jobs/
│   ├── TriggerLangdockAgent.php         # Langdock-Agent auslösen (ShouldBeUnique)
│   └── IngestPaperJob.php               # Ollama-Embedding + pgvector-Insert
├── Models/
│   ├── User.php, Contact.php, Webhook.php, ChatMessage.php, Consent.php, PageView.php
│   └── Recherche/
│       ├── Projekt.php                  # Kernmodell (user_id, titel, forschungsfrage)
│       ├── Phase.php                    # Phasen-Tracking (phase_nr 1–8)
│       ├── P1*.php … P8*.php            # 29 Phasen-spezifische Models
│       └── PaperEmbedding.php           # pgvector Embeddings
├── Policies/
│   └── ProjektPolicy.php                # Owner-basiert: nur eigene Projekte
└── Providers/
    └── Filament/AdminPanelProvider.php  # Filament Admin-Konfiguration

resources/views/
├── livewire/recherche/
│   ├── research-input.blade.php         # Volt-Komponente: Forschungsfrage eingeben
│   ├── projekt-liste.blade.php          # Volt-Komponente: Projektübersicht
│   └── projekt-detail.blade.php        # Volt-Komponente: Phasen-Detail
└── livewire/chat/
    └── big-research-chat.blade.php      # Volt-Komponente: KI-Chat-Interface

database/migrations/
├── 2026_03_31_099999_create_recherche_tables_sqlite.php  # SQLite-Fallback für Tests
├── 2026_03_31_100000_*                  # pgvector Extensions + Custom Enums
├── 2026_03_31_100001_*                  # projekte + phasen Tabellen
└── 2026_03_31_1000{02–09}_*            # P1–P8 Phasentabellen
```

---

## 4. Datenmodell (kritische Details)

### 4.1 Primary Keys

```php
// Domain-Models: UUID via HasUuids Trait
use Illuminate\Database\Eloquent\Concerns\HasUuids;

// User: Standard auto-increment (Fortify-Kompatibilität — NICHT ändern)
```

### 4.2 Timestamps-Konvention

Recherche-Models verwenden **keine** Laravel-Standard-Timestamps:

```php
// RICHTIG in Recherche-Models:
public $timestamps = false;
protected $casts = [
    'erstellt_am'    => 'datetime',
    'letztes_update' => 'datetime',
];

// FALSCH — niemals in Recherche-Models:
// created_at / updated_at
```

### 4.3 Custom Enums (PostgreSQL-nativ)

Folgende Enums existieren in der DB (`2026_03_31_100000_*`):

| Enum-Typ | Werte |
|---|---|
| `phase_status` | `offen`, `in_bearbeitung`, `abgeschlossen` |
| `review_typ` | `systematic_review`, `scoping_review`, `evidence_map` |
| `strukturmodell` | `PICO`, `SPIDER`, `PICOS` |
| `kriterium_typ` | `einschluss`, `ausschluss` |
| `screening_level` | `L1_titel_abstract`, `L2_volltext` |
| `screening_entscheidung` | `eingeschlossen`, `ausgeschlossen`, `unklar` |
| `rob_tool` | `RoB2`, `ROBINS-I`, `CASP_qualitativ`, `AMSTAR2`, … |
| `rob_urteil` | `niedrig`, `moderat`, `hoch`, `kritisch`, `nicht_bewertet` |
| `synthese_methode` | `meta_analyse`, `narrative_synthese`, … |
| `grade_urteil` | `stark`, `moderat`, `schwach`, `sehr_schwach` |
| `studientyp` | `RCT`, `nicht_randomisiert`, `qualitativ`, … |
| `tool_empfehlung` | `Rayyan`, `Covidence`, `EPPI_Reviewer`, … |

**Neue Enums** müssen per Raw-SQL in der Migration angelegt werden:

```php
// up():
DB::statement("CREATE TYPE mein_enum AS ENUM ('wert1', 'wert2')");
// down():
DB::statement("DROP TYPE IF EXISTS mein_enum CASCADE");
```

### 4.4 pgvector (Vektor-Embeddings)

```php
// PaperEmbedding — embedding-Spalte ist pgvector, kein Eloquent-Cast verfügbar
// Immer Raw SQL verwenden:
DB::statement(
    'INSERT INTO paper_embeddings (..., embedding) VALUES (..., ?::vector)',
    [..., '[0.1,0.2,...]']
);

// Vektorsuche (Cosine Distance):
DB::select(
    'SELECT *, 1 - (embedding <=> ?::vector) AS similarity FROM paper_embeddings ORDER BY embedding <=> ?::vector LIMIT ?',
    [$vectorLiteral, $vectorLiteral, $limit]
);
```

### 4.5 Relationen in Projekt.php

Alle P1–P8 Relationen sind im `Projekt`-Model definiert. `P6Qualitaetsbewertungen` und `P8Suchprotokolle` nutzen `HasManyThrough` (via P5Treffer bzw. P4Suchstring), da diese Tabellen kein direktes `projekt_id` FK haben.

---

## 5. Authentifizierung & Autorisierung

### 5.1 Laravel Fortify (Auth)

- Login, Register, Passwort-Reset, 2FA: **plain Blade** — kein Livewire
- Views: `resources/views/livewire/auth/*.blade.php`
- Konfiguration: `config/fortify.php`

### 5.2 Middleware-Stack

| Middleware | Zweck | Einsatzort |
|---|---|---|
| `auth` | Eingeloggter User | Alle `/dashboard`, `/recherche/*`, `/settings/*` |
| `verified` | E-Mail verifiziert | `/dashboard` |
| `password.confirm` | Passwort-Bestätigung | 2FA-Einstellungen |
| `VerifyLangdockSignature` | HMAC + Replay-Schutz | `POST /api/webhooks/langdock` |
| `VerifyMcpToken` | Bearer-Token | `/mcp/sse` (Nginx-Proxy) |

### 5.3 ProjektPolicy (Owner-Only)

```php
// Zugriff NUR für den Besitzer — immer Policy nutzen, nie direkt prüfen:
$this->authorize('view', $projekt);    // wirft 403 wenn nicht owner
$this->authorize('update', $projekt);
$this->authorize('delete', $projekt);
```

---

## 6. KI-Integration (kritischer Pfad)

### 6.1 Langdock-Agent auslösen

```
User gibt Forschungsfrage ein
    → Volt-Komponente (research-input.blade.php)
    → TriggerLangdockAgent::dispatch($userId, $projektId, $eingabe)
    → Redis Queue
    → HTTP POST an config('services.langdock.webhook_url')
    → Langdock-Agent liest DB via /mcp/sse
```

**Wichtig:** `TriggerLangdockAgent` implementiert `ShouldBeUnique` mit `uniqueId() = $projektId`.
Das bedeutet: Pro Projekt kann nur **ein** Job gleichzeitig in der Queue sein (`uniqueFor = 300s`).

### 6.2 Eingehende Langdock-Webhooks

```
Langdock sendet Ergebnis via POST /api/webhooks/langdock
    → VerifyLangdockSignature (HMAC-SHA256 + Timestamp ±5min + Cache-Nonce)
    → LangdockWebhookController::handle()
    → Validierung: user_id, projekt_id, eingabe
    → TriggerLangdockAgent::dispatch() (erneut in Queue)
```

### 6.3 Webhook-Sicherheit (NICHT verändern ohne Rücksprache)

Die `VerifyLangdockSignature`-Middleware implementiert drei Schutzebenen:

1. **HMAC-SHA256** — `hash_hmac('sha256', $timestamp . '.' . $body, $secret)`
2. **Timestamp-Validierung** — max. 5 Minuten Toleranz (`X-Langdock-Timestamp`)
3. **Cache-Nonce** — jede Signatur wird einmalig in Redis gespeichert (verhindert Replay-Angriffe)

### 6.4 Paper-Ingestion (Ollama)

```
POST /paper-mcp/ingest
    → PaperRagController::ingest()
    → IngestPaperJob::dispatch(paperId, source, title, text, projektId, metadata)
    → Redis Queue
    → Text wird in Chunks aufgeteilt (500 Wörter, 100 Überlappung)
    → Für jeden Chunk: HTTP POST → config('services.ollama.url')/api/embeddings
    → pgvector INSERT (Raw SQL)
```

**Konfigurationsvariablen für KI:**

```env
LANGDOCK_API_KEY=...
LANGDOCK_WEBHOOK_URL=...
LANGDOCK_SECRET=...          # HMAC-Geheimnis für Webhook-Signatur
MCP_AUTH_TOKEN=...           # Bearer-Token für /mcp/sse
OLLAMA_URL=http://localhost:11434  # Ollama Embedding Service
LANGDOCK_DB_HOST=...
LANGDOCK_DB_USER=langdock_agent   # eingeschränkter DB-User
LANGDOCK_DB_PASSWORD=...
```

---

## 7. Livewire / Volt — Konventionen

```php
// Inline-Komponente (IMMER diese Form):
<?php
new class extends Component {
    public string $property = '';

    public function save(): void
    {
        // ...
        $this->redirect(route('recherche'), navigate: true);
    }
};
?>
<div>
    <input wire:model="property">
    <button wire:click="save">Speichern</button>
</div>
```

**Verbote:**
- Kein Alpine.js (`x-data`, `x-bind`, etc.) — nur Livewire-Direktiven
- Kein `redirect()` helper — immer `$this->redirect(route(...), navigate: true)`
- Kein Routing mit `Route::get()` für Volt-Komponenten — immer `Volt::route()`

---

## 8. Filament Admin-Panel

- Zugang: `/admin` (nur Filament-Admins)
- Ressourcen: `ContactResource`, `UserResource`
- Formulare: **Schema-basiert** (`Filament\Schemas\Schema`)
- Datum-Format: `d.m.Y H:i` (Deutsch)
- NavigationLabels: **Deutsch**

---

## 9. Datenbankmigrationen — Regeln

1. **Migration immer in separatem Commit** vor Code-Änderungen
2. **PostgreSQL-Guards** für pgvector/Enum-Migrationen:
   ```php
   public function up(): void {
       if (DB::getDriverName() !== 'pgsql') return;
       // ...
   }
   ```
3. **SQLite-Fallback** für Tests existiert in `2026_03_31_099999_*` — dort neue Test-Tabellen ergänzen wenn nötig
4. **`langdock_agent`-User** bei neuen Tabellen: SELECT-Berechtigung prüfen (`database/sql/create_langdock_agent_user.sql`)
5. **Foreign Keys**: `foreignId('user_id')->constrained()->cascadeOnDelete()`

---

## 10. Testing

### 10.1 Framework und Stil

```php
// RICHTIG — Pest-Syntax:
test('ein user kann sein projekt sehen', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    // ...
    expect($response->status())->toBe(200);
});

// FALSCH — niemals PHPUnit-Klassen-Stil in diesem Projekt
```

### 10.2 SQLite in Tests

Tests laufen mit **SQLite in-memory** — pgvector und PostgreSQL-Enums sind nicht verfügbar.

```php
// Queue testen:
Queue::fake();
// Dann: prüfen ob Job dispatched wurde:
Queue::assertPushed(TriggerLangdockAgent::class);

// Config überschreiben:
Config::set('services.langdock.secret', 'test-secret');

// Volt-Komponenten:
Volt::test('recherche.research-input')
    ->set('forschungsfrage', 'Meine Frage')
    ->call('submit')
    ->assertRedirect(route('recherche'));
```

### 10.3 Testabdeckung (Stand: April 2026)

| Bereich | Status |
|---|---|
| Auth (Login, Register, 2FA, Passwort) | ✅ vollständig |
| Kontaktformular | ✅ vollständig |
| ProjektPolicy | ✅ vollständig |
| Webhook-Sicherheit | ✅ vollständig |
| Recherche P1–P4 (Livewire CRUD) | ✅ vollständig |
| Recherche P5–P8 (Livewire CRUD) | ✅ vollständig |
| Admin-Panel | ❌ fehlend |

### 10.4 Lokal ausführen

```bash
composer test                                          # lokal ohne Docker
docker compose run --rm php-test vendor/bin/pest       # Docker (empfohlen)
```

---

## 11. Bekannte Muster und Fallstricke

### 11.1 Ollama-URL — konfigurierbar

Die Ollama-URL wird über `config('services.ollama.url')` geladen (Default: `http://localhost:11434`).
Umgebungsvariable: `OLLAMA_URL`.

### 11.2 Fehlende Relationen in Projekt.php

P2–P8 Relationen sind vollständig definiert. `P6Qualitaetsbewertungen` und `P8Suchprotokolle` nutzen `HasManyThrough` (via P5Treffer bzw. P4Suchstring), da diese Tabellen kein direktes `projekt_id` FK haben.

### 11.3 Raw SQL für pgvector ist Absicht

`DB::statement()` und `DB::select()` mit `::vector` Cast sind kein Code-Smell — Eloquent unterstützt pgvector nicht nativ. Dieses Muster beibehalten.

### 11.4 Activity Logging (Spatie)

```php
// RICHTIG — Namespace-Pfad:
use Spatie\Activitylog\Models\Concerns\LogsActivity;

// FALSCH:
use Spatie\Activitylog\Traits\LogsActivity;
```

Nur `Projekt` und `User` nutzen Activity-Logging. Für neue Models: `LogsActivity` Trait + `getActivitylogOptions()` implementieren.

### 11.5 composer.json setup-Script

`composer setup` führt `php artisan migrate --force` aus — das ist für die lokale Entwicklung mit SQLite ausgelegt. In Produktionsumgebung nur `./deploy.sh` verwenden.

---

## 12. Git-Konventionen

| Typ | Branch-Prefix | Commit-Präfix |
|---|---|---|
| Neues Feature | `feature/` | `feat:` |
| Bugfix | `fix/` | `fix:` |
| Dokumentation | `docs/` | `docs:` |
| Refactoring | `refactor/` | `refactor:` |

**Merge-Fluss:**
```
feature/* / fix/*  →  develop  →  main (manuelles Deploy via deploy.sh)
```

- **Kein Direkt-Merge auf `main`**
- **Squash-Merge** bevorzugt
- PRs müssen grüne CI-Checks haben (Tests + Lint)
- Migrations **immer** in separatem Commit **vor** Code-Änderungen

---

## 13. Deployment

```bash
./deploy.sh                    # Vollständiges Deployment 
./deploy.sh --skip-build       # Ohne Docker-Rebuild
./deploy.sh --skip-migrate     # Ohne Migrationen
```

**Kein automatisches CI/CD-Deployment** — Deploy erfolgt ausschließlich manuell.
Keine `deploy.yml` GitHub Actions erstellen.

---

## 14. Kommunikationssprache

- **Deutsch:** Kommentare, Commit-Messages-Beschreibungen, Issue-Texte, Kommunikation
- **Englisch:** Code (Variablen, Methoden, Klassen), Git-Commit-Präfixe (`feat:`, `fix:` etc.)