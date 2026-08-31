#!/usr/bin/env bash
# D-39: HTTP 基线冒烟（目标 >1000 QPS）
set -euo pipefail

URL="${1:-http://127.0.0.1:8088/api/health/live}"
REQUESTS="${REQUESTS:-2000}"
CONCURRENCY="${CONCURRENCY:-50}"

echo "=== D-39 基线冒烟 ==="
echo "URL: $URL  Requests: $REQUESTS  Concurrency: $CONCURRENCY"

curl -sf "$URL" >/dev/null || { echo "无法连接，请先运行 scripts/benchmark-up.sh"; exit 1; }

START=$(date +%s.%N)

if command -v ab >/dev/null 2>&1; then
    ab -n "$REQUESTS" -c "$CONCURRENCY" -q "$URL" | tee /tmp/bench-ab.txt
    QPS=$(grep 'Requests per second' /tmp/bench-ab.txt | awk '{print $4}' | cut -d. -f1)
else
    echo "使用 curl 并行（安装 apache2-utils/ab 可获得更准确结果）"
    seq 1 "$REQUESTS" | xargs -P "$CONCURRENCY" -I{} curl -sf -o /dev/null "$URL" || true
    END=$(date +%s.%N)
    ELAPSED=$(echo "$END - $START" | bc)
    QPS=$(echo "scale=1; $REQUESTS / $ELAPSED" | bc)
    echo "QPS ≈ $QPS"
fi

BASELINE=1000
if [ "${QPS:-0}" -ge "$BASELINE" ] 2>/dev/null; then
    echo "基线达标 ✅ (>= $BASELINE QPS)"
else
    echo "未达基线 ⚠️ (目标 >= $BASELINE QPS)"
    exit 1
fi
