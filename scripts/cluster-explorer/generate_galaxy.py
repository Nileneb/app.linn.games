#!/usr/bin/env python3
"""
generate_galaxy.py — Cluster Explorer Galaxy Map Generator
============================================================
Liest p2_cluster + paper_embeddings aus PostgreSQL,
berechnet UMAP-3D-Positionen aus den pgvector-Embeddings,
schreibt galaxy-data.json für Three.js.

Setup:
    pip install psycopg2-binary umap-learn numpy scikit-learn

Usage:
    python generate_galaxy.py --projekt-id <uuid> --out public/galaxy-data.json
    python generate_galaxy.py --all --out public/galaxy-data.json
"""

import argparse
import json
import os
import sys

import numpy as np
import psycopg2
import psycopg2.extras
from sklearn.preprocessing import normalize

try:
    import umap
except ImportError:
    print("FEHLER: umap-learn nicht installiert. Bitte: pip install umap-learn")
    sys.exit(1)

# ── DB-Verbindung aus .env lesen ──────────────────────────────────────────────

def load_env(path=".env"):
    env = {}
    try:
        with open(path) as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env[k.strip()] = v.strip().strip('"').strip("'")
    except FileNotFoundError:
        pass
    return env

def get_db_conn(env):
    return psycopg2.connect(
        host=env.get("DB_HOST") or os.getenv("DB_HOST", "127.0.0.1"),
        port=int(env.get("DB_PORT") or os.getenv("DB_PORT", 5432)),
        dbname=env.get("DB_DATABASE") or os.getenv("DB_DATABASE", "app_linn"),
        user=env.get("DB_USERNAME") or os.getenv("DB_USERNAME", "postgres"),
        password=env.get("DB_PASSWORD") or os.getenv("DB_PASSWORD", ""),
    )

# ── Daten laden ───────────────────────────────────────────────────────────────

def load_clusters(conn, projekt_id=None):
    """Lädt p2_cluster-Einträge."""
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        if projekt_id:
            cur.execute(
                "SELECT * FROM p2_cluster WHERE projekt_id = %s ORDER BY treffer_schaetzung DESC",
                (projekt_id,)
            )
        else:
            cur.execute("SELECT * FROM p2_cluster ORDER BY treffer_schaetzung DESC LIMIT 200")
        return cur.fetchall()

def load_embeddings(conn, projekt_id=None):
    """
    Lädt paper_embeddings + cluster-Zuordnung.
    Gibt zurück: Liste von {paper_id, cluster_id, embedding (np.array)}
    """
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        if projekt_id:
            cur.execute("""
                SELECT
                    pe.paper_id,
                    pt.cluster_id,
                    pe.embedding::text AS embedding_text
                FROM paper_embeddings pe
                JOIN p5_treffer pt ON pt.id = pe.paper_id
                WHERE pt.projekt_id = %s
                  AND pe.embedding IS NOT NULL
                LIMIT 2000
            """, (projekt_id,))
        else:
            cur.execute("""
                SELECT
                    pe.paper_id,
                    NULL AS cluster_id,
                    pe.embedding::text AS embedding_text
                FROM paper_embeddings pe
                WHERE pe.embedding IS NOT NULL
                LIMIT 2000
            """)
        rows = cur.fetchall()

    result = []
    for row in rows:
        try:
            # pgvector gibt embedding als '[0.1,0.2,...]' zurück
            raw = row["embedding_text"].strip("[]").split(",")
            vec = np.array([float(x) for x in raw], dtype=np.float32)
            result.append({
                "paper_id":  str(row["paper_id"]),
                "cluster_id": str(row["cluster_id"]) if row["cluster_id"] else None,
                "embedding":  vec,
            })
        except Exception as e:
            print(f"  Warnung: Embedding-Parse-Fehler für {row['paper_id']}: {e}")
    return result

# ── UMAP-Berechnung ───────────────────────────────────────────────────────────

def compute_umap_3d(embeddings_data):
    """
    Reduziert hochdimensionale Embeddings auf 3D via UMAP.
    Gibt np.array (N, 3) zurück — normalisiert auf [-50, 50].
    """
    if not embeddings_data:
        return np.array([])

    vecs = np.stack([e["embedding"] for e in embeddings_data])
    vecs = normalize(vecs, norm="l2")

    print(f"  UMAP: {vecs.shape[0]} Paper, {vecs.shape[1]} Dimensionen → 3D ...")

    reducer = umap.UMAP(
        n_components=3,
        n_neighbors=min(15, len(vecs) - 1),
        min_dist=0.1,
        metric="cosine",
        random_state=42,
        verbose=False,
    )
    coords = reducer.fit_transform(vecs)

    # Auf [-60, 60] skalieren
    for axis in range(3):
        lo, hi = coords[:, axis].min(), coords[:, axis].max()
        if hi > lo:
            coords[:, axis] = (coords[:, axis] - lo) / (hi - lo) * 120 - 60

    return coords

def compute_cluster_centroids(embeddings_data, coords, clusters):
    """
    Berechnet den 3D-Schwerpunkt jedes Clusters aus den UMAP-Koordinaten.
    Fallback: zufällige Spiralposition wenn keine Paper vorhanden.
    """
    cluster_ids = {str(c["id"]): i for i, c in enumerate(clusters)}
    centroids   = {cid: [] for cid in cluster_ids}

    for i, e in enumerate(embeddings_data):
        cid = e["cluster_id"]
        if cid and cid in centroids:
            centroids[cid].append(coords[i])

    result = {}
    for cid, points in centroids.items():
        if points:
            result[cid] = np.mean(points, axis=0).tolist()
        else:
            # Spiralförmige Fallback-Position
            idx = cluster_ids[cid]
            angle = idx * 2.4  # goldener Winkel
            r = 25 + idx * 8
            result[cid] = [
                r * np.cos(angle),
                (idx % 3 - 1) * 15,
                r * np.sin(angle),
            ]
    return result

# ── Outlier-Detection ─────────────────────────────────────────────────────────

def detect_outliers(embeddings_data, coords, centroids, threshold=25.0):
    """
    Findet Paper, deren 3D-Koordinate weit vom Cluster-Zentroid liegt.
    Diese werden im Spiel als 'Anomalien' angezeigt (rote Oktaeder).
    threshold: Euklidische Distanz in 3D-Einheiten.
    """
    outliers = []
    for i, e in enumerate(embeddings_data):
        cid = e["cluster_id"]
        if cid and cid in centroids:
            c = np.array(centroids[cid])
            p = coords[i]
            dist = float(np.linalg.norm(p - c))
            if dist > threshold:
                outliers.append({
                    "paper_id":   e["paper_id"],
                    "cluster_id": cid,
                    "position":   p.tolist(),
                    "distance":   round(dist, 2),
                })
    # Nur die extremsten 20 Outlier als Anomalien
    outliers.sort(key=lambda x: x["distance"], reverse=True)
    return outliers[:20]

# ── JSON-Output ───────────────────────────────────────────────────────────────

RELEVANZ_COLOR = {
    "hoch":    "#6366f1",
    "mittel":  "#10b981",
    "niedrig": "#f59e0b",
}

def build_output(clusters, embeddings_data, coords, centroids, outliers):
    cluster_nodes = []
    for c in clusters:
        cid = str(c["id"])
        pos = centroids.get(cid, [0, 0, 0])
        relevanz = str(c.get("relevanz") or "mittel").lower()
        color    = RELEVANZ_COLOR.get(relevanz, "#8b5cf6")

        cluster_nodes.append({
            "id":         cid,
            "label":      c["cluster_label"],
            "desc":       c.get("beschreibung") or "",
            "treffer":    int(c.get("treffer_schaetzung") or 10),
            "relevanz":   relevanz,
            "color":      color,
            "position":   [round(x, 2) for x in pos],
            "size":       max(3, min(12, int(c.get("treffer_schaetzung") or 10) / 12)),
        })

    # Paper-Wolke (alle Punkte als kleine Partikel)
    paper_points = []
    for i, e in enumerate(embeddings_data):
        paper_points.append({
            "paper_id":   e["paper_id"],
            "cluster_id": e["cluster_id"],
            "position":   [round(x, 2) for x in coords[i].tolist()],
        })

    # Ähnlichkeits-Kanten zwischen Clustern (Cosine-Ähnlichkeit der Centroids)
    edges = []
    cids  = list(centroids.keys())
    for i in range(len(cids)):
        for j in range(i+1, len(cids)):
            a = np.array(centroids[cids[i]])
            b = np.array(centroids[cids[j]])
            dist = float(np.linalg.norm(a - b))
            sim  = round(max(0, 1 - dist/80), 3)
            if sim > 0.3:  # Nur ähnliche Cluster verbinden
                edges.append({
                    "source":     cids[i],
                    "target":     cids[j],
                    "similarity": sim,
                })

    return {
        "meta": {
            "generated_at":   __import__("datetime").datetime.now().isoformat(),
            "cluster_count":  len(cluster_nodes),
            "paper_count":    len(paper_points),
            "outlier_count":  len(outliers),
            "umap_dims":      3,
        },
        "clusters":    cluster_nodes,
        "papers":      paper_points,
        "edges":       edges,
        "anomalies":   outliers,   # Outlier → rote Oktaeder im Spiel
        "dark_matter": [p for p in paper_points if p["cluster_id"] is None],  # Excluded papers
    }

# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="Generate galaxy-data.json from pgvector embeddings")
    parser.add_argument("--projekt-id", help="Projekt-UUID (ohne: alle Projekte)")
    parser.add_argument("--out", default="public/galaxy-data.json", help="Output JSON path")
    parser.add_argument("--env", default=None, help=".env Pfad (optional — fällt auf Umgebungsvariablen zurück)")
    args = parser.parse_args()

    env = load_env(args.env) if args.env else {}
    print(f"Verbinde mit DB: {env.get('DB_HOST') or os.getenv('DB_HOST', '127.0.0.1')}:{env.get('DB_PORT') or os.getenv('DB_PORT', 5432)}/{env.get('DB_DATABASE') or os.getenv('DB_DATABASE', '?')}")

    conn = get_db_conn(env)
    try:
        print("Lade Cluster ...")
        clusters = load_clusters(conn, args.projekt_id)
        print(f"  {len(clusters)} Cluster gefunden")

        print("Lade Paper-Embeddings ...")
        embeddings_data = load_embeddings(conn, args.projekt_id)
        print(f"  {len(embeddings_data)} Embeddings geladen")

        if embeddings_data:
            coords = compute_umap_3d(embeddings_data)
        else:
            print("  Keine Embeddings — verwende Fallback-Positionen")
            coords = np.zeros((0, 3))

        print("Berechne Cluster-Centroids ...")
        centroids = compute_cluster_centroids(embeddings_data, coords, clusters)

        print("Detektiere Outlier-Anomalien ...")
        outliers = detect_outliers(embeddings_data, coords, centroids) if len(coords) > 0 else []
        print(f"  {len(outliers)} Anomalien gefunden")

        output = build_output(clusters, embeddings_data, coords, centroids, outliers)

        os.makedirs(os.path.dirname(args.out) if os.path.dirname(args.out) else ".", exist_ok=True)
        with open(args.out, "w", encoding="utf-8") as f:
            json.dump(output, f, ensure_ascii=False, indent=2)

        print(f"\n✓ Galaxy-Daten geschrieben: {args.out}")
        print(f"  Cluster:   {output['meta']['cluster_count']}")
        print(f"  Paper:     {output['meta']['paper_count']}")
        print(f"  Anomalien: {output['meta']['outlier_count']}")
        print(f"  Kanten:    {len(output['edges'])}")
        print(f"\nNächster Schritt: Three.js lädt {args.out} per fetch()")

    finally:
        conn.close()

if __name__ == "__main__":
    main()
