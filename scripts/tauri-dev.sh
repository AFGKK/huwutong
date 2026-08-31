#!/usr/bin/env bash
# D-33: Tauri 轻量 License 查看器 — 开发启动
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TAURI_DIR="$ROOT/desktop/tauri"
API_BASE="${HWT_API_BASE:-http://127.0.0.1:8000}"

echo "=== D-33 Tauri License 查看器 ==="

if ! curl -sf --connect-timeout 3 "${API_BASE}/api/health/live" >/dev/null 2>&1; then
    echo "警告: 后端未响应，请先 php artisan serve --port=8000"
fi

if ! command -v cargo >/dev/null 2>&1; then
    echo "未检测到 Rust/cargo，请安装: https://rustup.rs"
    exit 1
fi

cd "$TAURI_DIR"
if [ ! -d node_modules/@tauri-apps/cli ]; then
    echo "安装 Tauri CLI..."
    npm install
fi

export HWT_API_BASE="$API_BASE"
npm run dev
