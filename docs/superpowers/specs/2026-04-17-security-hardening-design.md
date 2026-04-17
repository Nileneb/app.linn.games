# Security Hardening — Design Spec
**Issue #215 | Scope: Prio 1–4 | Datum: 2026-04-17**

---

## Kontext

Am 17.04.2026 wurde ein Bot-Angriff via Tor-Exit-Node auf den Registrierungsendpunkt erkannt. Bestehende Maßnahmen (Honeypot, Rate-Limiting, GeoIP, Country-Blocking) haben ausgelöst, reichen aber nicht aus.

Dieses Spec implementiert Prio 1–4 aus Issue #215 und legt gleichzeitig das Fundament für das vollständige Confidence-Score-System aus `angreifer-deanonymisierung-techniken.md`.

Prio 5 (Gamified CAPTCHA) wird als separates Sub-Issue behandelt.

---

## Datenmodell

### Neue Tabelle: `pending_registrations`

| Feld | Typ | Details |
|---|---|---|
| `id` | uuid PK | |
| `name` | string 255 | |
| `email` | string 255 unique | |
| `password` | string | gehasht via Hash::make |
| `forschungsfrage` | text | |
| `forschungsbereich` | string | |
| `erfahrung` | string | |
| `token` | uuid unique | E-Mail-Verifikationstoken |
| `token_expires_at` | timestamp | now() + 24h |
| `confidence_score` | int default 0 | Summe aller Signal-Beiträge |
| `score_breakdown` | json | `{"timing":0,"timezone":0,"tor":0,"disposable":0}` |
| `registration_ip` | string nullable | |
| `registration_country_code` | string 2 nullable | |
| `registration_country_name` | string nullable | |
| `registration_city` | string nullable | |
| `user_agent` | string 512 nullable | |
| `needs_review` | bool default false | Score 40–79 |
| `status` | enum | `pending_email`, `verified`, `rejected` |
| `expires_at` | timestamp | now() + 48h (Cleanup) |
| `created_at`, `updated_at` | timestamps | |

---

## Confidence Score System

### JS-Signale (Hidden Fields im Formular)

| Signal | Feld | Erhebung | Score-Beitrag |
|---|---|---|---|
| Timing | `_timing` | `Date.now() - pageLoadTime` bei Submit | < 2000ms → +50 |
| Timezone | `_tz` | `Intl.DateTimeFormat().resolvedOptions().timeZone` | ≠ GeoIP-Region → +20 |

JS setzt beide Felder beim Submit-Event. Fehlen die Felder (Bots ohne JS) → Timing wird als 0 gewertet → automatisch +50.

### Server-Signale

| Signal | Quelle | Score-Beitrag |
|---|---|---|
| Tor/VPN-IP | `TorDetectionService` (Redis) | erkannt → +15 |
| Disposable Email | `propaganistas/laravel-disposable-email` | erkannt → +40 |

### Thresholds

```
Score ≥ 80  → PendingRegistration status=rejected, logge RegistrationAttempt, generische Fehlermeldung
Score 40-79 → PendingRegistration mit needs_review=true (Verifikations-Mail trotzdem senden)
Score < 40  → normaler Flow
```

`score_breakdown` JSON erlaubt späteres Hinzufügen weiterer Signale (WebRTC, JA3, Canvas-Fingerprint) ohne Schema-Migration.

---

## Komponenten

### `CreatePendingRegistration` (ersetzt `CreateNewUser`)

Ablauf:
1. Honeypot-Check (bestehendes `website`-Feld)
2. Rate-Limiting (bestehend)
3. Validation (bestehende Felder + `_timing`, `_tz`)
4. Disposable-Email-Check → Score
5. GeoIP-Lookup → Timezone-Mismatch-Check → Score
6. Tor-Detection → Score
7. Timing-Check → Score
8. Score ≥ 80 → `RegistrationAttempt` loggen + Exception
9. `PendingRegistration` erstellen (needs_review wenn Score 40–79)
10. `PendingRegistrationVerificationMail` dispatchen

### `TorDetectionService`

- `isKnownTorOrVpnIp(string $ip): bool`
- Liest Redis-Set `security:tor_nodes`
- Fallback: false (kein Redis-Fehler blockiert Registrierung)

### Artisan: `security:sync-tor-nodes`

- Fetched `https://check.torproject.org/torbulkexitlist`
- Parst IPs zeilenweise
- Speichert als Redis-Set mit TTL 6h
- Loggt Anzahl importierter Nodes

### Artisan: `security:prune-pending-registrations`

- Löscht alle `PendingRegistration` mit `expires_at < now()`

### `VerifyPendingRegistrationController`

- `GET /register/verify/{token}`
- Validiert: Token existiert, `status = pending_email`, `token_expires_at > now()`
- Erstellt `User` mit `status = waitlisted`
- Dispatcht `ReviewRegistrationJob`
- Löscht `PendingRegistration`
- Redirect → Login mit Success-Message

### `PendingRegistrationVerificationMail`

- Mailable mit Button → Verifikations-URL
- Absender: bestehende Mail-Config
- Expires-Hinweis: "Link gültig für 24 Stunden"

### `register.blade.php` Änderungen

```javascript
<script>
const _pageLoad = Date.now();
document.querySelector('form').addEventListener('submit', () => {
    document.getElementById('_timing').value = Date.now() - _pageLoad;
    document.getElementById('_tz').value =
        Intl.DateTimeFormat().resolvedOptions().timeZone;
});
</script>
```

Zwei hidden inputs: `_timing` (default 0), `_tz` (default leer).

---

## Routen

```
GET  /register/verify/{token}  →  VerifyPendingRegistrationController
```

Bestehende Registrierungsrouten bleiben unverändert.

---

## Scheduler (`console.php`)

```php
Schedule::command('security:sync-tor-nodes')->everySixHours();
Schedule::command('disposable:update')->weekly();
Schedule::command('security:prune-pending-registrations')->daily();
```

---

## Filament-Dashboard

Neue `PendingRegistrationResource`:
- Spalten: email, confidence_score, needs_review (Badge), status, created_at
- Filter: needs_review=true, status
- Actions: manuell verifizieren / ablehnen

---

## Abhängigkeiten

- Package: `propaganistas/laravel-disposable-email`
- Redis: bereits im Stack vorhanden
- GeoIP: `GeoIpService` bereits vorhanden
- Mail: bestehende Fortify-Mail-Config

---

## Nicht im Scope (dieses Spec)

- Prio 5: Gamified CAPTCHA → separates Issue
- WebRTC-Probe, Canvas-Fingerprint, JA3 → Woche 1–2 laut `angreifer-deanonymisierung-techniken.md`
- Behavioral Biometrics → Woche 3
- DNS-Leak-Probe → Optional
