#!/usr/bin/env bash
# D-32: Electron 管理壳 — 开发启动
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ELECTRON_DIR="$ROOT/desktop/electron"
ADMIN_URL="${HWT_ADMIN_URL:-http://127.0.0.1:8000/build}"

echo "=== D-32 Electron 管理壳 ==="
echo "Admin URL: $ADMIN_URL"

BASE="${ADMIN_URL%/build}"
BASE="${BASE%/}"
if ! curl -sf --connect-timeout 3 "${BASE}/api/health/live" >/dev/null 2>&1; then
    echo "警告: 后端未响应，请先 php artisan serve --port=8000"
fi

cd "$ELECTRON_DIR"
if [ ! -d node_modules/electron ]; then
    echo "安装 desktop/electron 依赖..."
    npm install
fi

export HWT_ADMIN_URL="$ADMIN_URL"
npm run dev
