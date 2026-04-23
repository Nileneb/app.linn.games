#!/usr/bin/env bash
# Benchmark mayring-api from outside — no GitHub Actions, no production runner.
# Usage: MCP_SERVICE_TOKEN=xxx ./scripts/benchmark.sh [API_URL]
# Default API_URL: http://localhost:6480
# Remote:  ssh u-server 'MCP_SERVICE_TOKEN=$(docker exec mayring-mayring-api-1 printenv MCP_SERVICE_TOKEN) bash -s' < scripts/benchmark.sh http://localhost:6480

set -euo pipefail

API="${1:-http://localhost:6480}"
TOKEN="${MCP_SERVICE_TOKEN:-}"

if [ -z "$TOKEN" ]; then
  echo "ERROR: MCP_SERVICE_TOKEN not set" >&2
  exit 1
fi

stats() {
  python3 -c "
import sys
v = sorted(float(l) for l in sys.stdin if l.strip())
if not v: print('avg=n/a p95=n/a'); exit()
n = len(v)
print(f'avg={sum(v)/n:.3f}s  p95={v[int(n*0.95)]:.3f}s  n={n}')
"
}

echo "=== Benchmark: $API ==="
echo ""

# 1 — Health latency (20 requests)
echo "--- /health (20 seq requests) ---"
TIMES=$(for _ in $(seq 1 20); do
  curl -sf -o /dev/null -w "%{time_total}\n" --max-time 10 "$API/health"
done)
echo "$TIMES" | stats

# 2 — /conversation/micro-batch latency (10 requests)
echo ""
echo "--- /conversation/micro-batch (10 seq requests) ---"
PAYLOAD='{"turns":[{"role":"user","content":"bench","timestamp":"2026-01-01T00:00:00Z"}],"session_id":"bench-lat","workspace_slug":"bench"}'
TIMES=$(for _ in $(seq 1 10); do
  curl -sf -o /dev/null -w "%{time_total}\n" --max-time 90 \
    -X POST "$API/conversation/micro-batch" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD"
done)
echo "$TIMES" | stats

# 3 — Concurrent throughput (ab if available)
echo ""
echo "--- Throughput (ab, 50 req c=5) ---"
if command -v ab &>/dev/null; then
  ab -n 50 -c 5 "$API/health" 2>&1 | grep -E "Requests per second|Failed requests"
else
  echo "ab not installed — install with: apt install apache2-utils"
fi

echo ""
echo "Done."
