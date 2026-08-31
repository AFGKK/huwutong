#!/usr/bin/env bash
# D-40: 5000 QPS 达标验证
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BENCH_BASE_URL:-}"
TARGET_QPS="${TARGET_QPS:-5000}"

if [ -z "$BASE_URL" ]; then
    if curl -sf http://127.0.0.1:8088/api/health/live >/dev/null 2>&1; then
        BASE_URL="http://127.0.0.1:8088/api"
    else
        BASE_URL="http://127.0.0.1:8000/api"
    fi
fi

echo "=== D-40 5000 QPS 验证 ==="
echo "Base URL: $BASE_URL"

REPORT_CONCURRENCY=100
REPORT_REQUESTS=5000
if [[ "$BASE_URL" == *":8000"* ]]; then
    REPORT_CONCURRENCY=5
    REPORT_REQUESTS=500
    echo "注意: artisan serve 单进程，HTTP 压测使用较低并发 ($REPORT_CONCURRENCY)"
fi

php artisan benchmark:report \
    --base-url="$BASE_URL" \
    --target-qps="$TARGET_QPS" \
    --requests="$REPORT_REQUESTS" \
    --concurrency="$REPORT_CONCURRENCY" \
    --try-k6

if command -v k6 >/dev/null 2>&1; then
    k6 run -e "BASE_URL=$BASE_URL" -e "TARGET_QPS=$TARGET_QPS" -e "DURATION=2m" \
        --summary-export=benchmarks/results/k6-qps-summary.json \
        benchmarks/k6/scripts/qps-target.js
fi

echo "报告: benchmarks/results/benchmark-result.json"
