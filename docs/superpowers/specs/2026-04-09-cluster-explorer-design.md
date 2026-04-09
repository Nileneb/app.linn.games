# Cluster Explorer — Design Spec

**Datum:** 2026-04-09

## Goal

Visualisiert `p2_cluster`-Daten + pgvector-Embeddings als navigierbare 3D-Galaxie mit eingebettetem Space-Shooter. Nutzer können ihre Forschungscluster erkunden und dabei interaktiv spielen. Zugang über `/projekte/{projekt}/galaxy`.

## Architecture

```
Browser
  fetch('/api/galaxy-data/{projekt_id}')
    → GalaxyDataController (Auth-Check via ProjektPolicy)
    → liest public/galaxy-data/{projekt_id}.json

GET /projekte/{projekt}/galaxy
  → GalaxyController::show()
  → resources/views/galaxy/show.blade.php
    → Three.js r128 (CDN)
    → Web Audio API (synthesized sound)
    → Spiellogik aus galaxy-v3.html
```

**Datengenerierung (Python):**
```
scripts/cluster-explorer/generate_galaxy.py
  → psycopg2 → PostgreSQL (p2_cluster, paper_embeddings, p5_treffer)
  → UMAP 3D (umap-learn, cosine metric, random_state=42)
  → public/galaxy-data/{projekt_id}.json
```

Artisan-Command `php artisan galaxy:generate {projekt_id}` wrapped den Python-Aufruf via `exec()`.

## Data Flow

1. Admin/Nutzer triggert `php artisan galaxy:generate {projekt_id}`
2. Python liest Cluster + Embeddings aus PostgreSQL
3. UMAP reduziert auf 3D ([-60, 60] skaliert)
4. Output: `public/galaxy-data/{projekt_id}.json`
5. Browser fetcht JSON → Three.js rendert Galaxy
6. Kein Server-State im Spiel — Scores nur in-session (localStorage)

## galaxy-data.json Schema

```json
{
  "meta": { "generated_at": "ISO8601", "cluster_count": 12, "paper_count": 450 },
  "clusters": [
    { "id": "uuid", "label": "string", "desc": "string", "treffer": 120,
      "relevanz": "hoch|mittel|niedrig", "color": "#6366f1",
      "position": [x, y, z], "size": 3-12 }
  ],
  "papers": [
    { "paper_id": "uuid", "cluster_id": "uuid|null", "position": [x, y, z] }
  ],
  "edges": [
    { "source": "uuid", "target": "uuid", "similarity": 0.0-1.0 }
  ],
  "anomalies": [ { "paper_id": "uuid", "cluster_id": "uuid", "position": [...], "distance": 28.4 } ],
  "dark_matter": [ { "paper_id": "uuid", "cluster_id": null, "position": [...] } ]
}
```

## New Files

| Datei | Zweck |
|-------|-------|
| `app/Http/Controllers/GalaxyController.php` | Show-Route, prüft ProjektPolicy, gibt View + Projekt-UUID zurück |
| `app/Http/Controllers/GalaxyDataController.php` | `GET /api/galaxy-data/{projekt_id}` — JSON mit Auth-Check |
| `app/Console/Commands/GenerateGalaxyData.php` | `galaxy:generate {projekt_id}` — exec() → Python |
| `resources/views/galaxy/show.blade.php` | Blade-Wrapper mit Three.js + Spiellogik (aus v3) |
| `routes/web.php` | `GET /projekte/{projekt}/galaxy` |
| `routes/api.php` | `GET /api/galaxy-data/{projekt_id}` |

## Game Features (aus galaxy-v3.html)

- **3D Navigation:** WASD + Maus, Shift = Boost
- **Waffen:** Laser (Linksklick), Rakete (R)
- **4 Enemy AIs:**
  - Anomaly (Oktaeder, rot) — Wander/Chase
  - Collision (Ikosaeder, orange) — Flank/Dive-Charge
  - Dark Matter (Tetraeder, lila) — Stealth + Teleport-Behind-Player
  - Boss (Dodecaeder, gold) — Orbit + Charge + Enemy Projectiles
- **Sound:** Web Audio API synthesized (Laser, Explosion, Damage, Ambient Drone, Combo)
- **Waves:** `3 + n*2` Gegner pro Welle, steigende Boss-Wahrscheinlichkeit
- **HUD:** Leben, Schild, Raketen, Score, Welle, Combo-Multiplikator (×1–×8)
- **Demo-Modus:** Dummy-JSON-Fallback wenn keine DB-Daten vorhanden

## Auth & Policy

- Route via `auth` Middleware + `ProjektPolicy::view()`
- API-Endpoint `GET /api/galaxy-data/{projekt_id}`: Bearer-Token (gleiche Middleware wie andere API-Routen) ODER Session-Auth
- Kein eigener Galaxy-spezifischer Policy-Check nötig — ProjektPolicy reicht

## Out of Scope (MVP)

- Persistente Highscores in DB
- Multiplayer
- Eigener Filament-Admin für Galaxy-Daten
- Automatische Regenerierung bei neuen Papers (manueller Artisan-Trigger reicht)
- Sound-Einstellungen im UI (nur Mute-Toggle)

## Demo-Fallback

Wenn `public/galaxy-data/{projekt_id}.json` nicht existiert, lädt das Frontend ein eingebettetes Dummy-JSON mit 8 Beispiel-Clustern auf Spiral-Positionen — Spiel bleibt vollständig spielbar.
