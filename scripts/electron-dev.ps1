# D-32: Electron 管理壳 — 开发启动
# 用法: powershell -ExecutionPolicy Bypass -File scripts/electron-dev.ps1

param(
    [string]$AdminUrl = "http://127.0.0.1:8000/build"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$ElectronDir = Join-Path $Root "desktop\electron"
Set-Location $Root

Write-Host "=== D-32 Electron 管理壳 ===" -ForegroundColor Cyan
Write-Host "Admin URL: $AdminUrl"

# 1. 确保 Laravel 后端可用
try {
    $base = $AdminUrl -replace '/build/?$', ''
    Invoke-WebRequest -Uri "$base/api/health/live" -UseBasicParsing -TimeoutSec 3 | Out-Null
} catch {
    Write-Host "后端未响应，请先运行: php artisan serve --port=8000" -ForegroundColor Yellow
}

# 2. 安装 Electron 依赖
Set-Location $ElectronDir
if (-not (Test-Path "node_modules\electron")) {
    Write-Host "安装 desktop/electron 依赖..." -ForegroundColor Yellow
    npm install
}

# 3. 启动
$env:HWT_ADMIN_URL = $AdminUrl
npm run dev
