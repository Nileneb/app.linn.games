# Security Hardening — Design Spec
**Issue #215 | Scope: Prio 1–4 | Datum: 2026-04-17**

> **Hinweis:** Implementierungsdetails (Score-Gewichtungen, Signale, Schwellenwerte)
> werden intern dokumentiert und nicht im öffentlichen Repo gepflegt.

---

## Übersicht

Reaktion auf Bot-Angriff vom 17.04.2026. Implementiert werden:

1. **E-Mail-Verifizierung vor Waitlist-Eintrag** — `PendingRegistration`-Model, kein User-Datensatz vor Bestätigung
2. **Bot-Detection-Signale** — unsichtbare JS-Checks beim Formular-Submit
3. **Tor/VPN-Exit-Node-Erkennung** — Redis-gecachte Node-Liste, stündlich aktualisiert
4. **Disposable-Email-Blocklist** — Package-basiert, wöchentlich aktualisiert

## Architektur

- `CreateNewUser` → `CreatePendingRegistration` (kein User bis E-Mail bestätigt)
- `GET /register/verify/{token}` → `VerifyPendingRegistrationController`
- `TorDetectionService` + Artisan Command `security:sync-tor-nodes`
- Filament-Resource für manuelle Review-Queue (`needs_review`-Flag)

## Abhängigkeiten

- `propaganistas/laravel-disposable-email`
- Redis (bereits im Stack)
- GeoIP-Service (bereits vorhanden)

## Nicht im Scope

- Prio 5 (Gamified CAPTCHA) → separates Issue
- Erweiterte Fingerprinting-Techniken → separate Implementierungsphasen
