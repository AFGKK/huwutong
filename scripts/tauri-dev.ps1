# D-33: Tauri 轻量 License 查看器 — 开发启动
param(
    [string]$ApiBase = "http://127.0.0.1:8000"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$TauriDir = Join-Path $Root "desktop\tauri"
Set-Location $TauriDir

Write-Host "=== D-33 Tauri License 查看器 ===" -ForegroundColor Cyan

try {
    Invoke-WebRequest -Uri "$ApiBase/api/health/live" -UseBasicParsing -TimeoutSec 3 | Out-Null
} catch {
    Write-Host "后端未响应，请先: php artisan serve --port=8000" -ForegroundColor Yellow
}

if (-not (Get-Command cargo -ErrorAction SilentlyContinue)) {
    Write-Host "未检测到 Rust/cargo，请安装: https://rustup.rs" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path "node_modules\@tauri-apps\cli")) {
    Write-Host "安装 Tauri CLI..." -ForegroundColor Yellow
    npm install
}

$env:HWT_API_BASE = $ApiBase
npm run dev
