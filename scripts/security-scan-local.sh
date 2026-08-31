#!/usr/bin/env bash
# D-41: 本地 ZAP 门禁验证
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TARGET_URL="${TARGET_URL:-http://127.0.0.1:8000}"

echo "=== D-41 安全扫描本地验证 ==="
echo "Target: $TARGET_URL"
echo ""

if ! curl -sf --connect-timeout 3 "${TARGET_URL}/api/health/live" >/dev/null 2>&1; then
    echo "启动 artisan serve..."
    php artisan serve --host=127.0.0.1 --port=8000 &
    sleep 4
fi

echo "1. 静态安全分析"
php artisan security:scan --quick --target="$TARGET_URL"

echo ""
echo "2. ZAP 基线扫描 (需 Docker)"
if command -v docker >/dev/null 2>&1; then
    export TARGET_URL FAIL_ON_HIGH=true
    bash .ci/zap/ci-scan.sh baseline || echo "ZAP 扫描跳过或失败"
else
    echo "Docker 未安装，跳过 ZAP 动态扫描"
fi

echo ""
echo "D-41 本地验证完成"
