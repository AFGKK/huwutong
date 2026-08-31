# D-39: 启动压测环境（Nginx + PHP-FPM + Redis + PostgreSQL）
# 用法: powershell -ExecutionPolicy Bypass -File scripts/benchmark-up.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$ComposeFile = "deploy/benchmark/docker-compose.benchmark.yml"

Write-Host "=== D-39 压测环境搭建 ===" -ForegroundColor Cyan

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "未检测到 Docker，请先安装 Docker Desktop。" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path ".env")) {
    Write-Host "创建 .env ..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    php artisan key:generate --force
}

Write-Host "启动 Nginx + PHP-FPM + Redis + PostgreSQL + Queue ..." -ForegroundColor Yellow
docker compose -f $ComposeFile up -d --build

Write-Host "等待服务就绪 ..." -ForegroundColor Yellow
$maxWait = 90
$elapsed = 0
$ready = $false
while ($elapsed -lt $maxWait) {
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:8088/api/health/live" -UseBasicParsing -TimeoutSec 3
        if ($r.StatusCode -eq 200) { $ready = $true; break }
    } catch {}
    Start-Sleep -Seconds 3
    $elapsed += 3
}

if (-not $ready) {
    Write-Host "健康检查超时，尝试初始化数据库后重试 ..." -ForegroundColor Yellow
    docker compose -f $ComposeFile exec -T app php artisan migrate --force 2>$null
    Start-Sleep -Seconds 5
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:8088/api/health/live" -UseBasicParsing -TimeoutSec 5
        $ready = ($r.StatusCode -eq 200)
    } catch {}
}

Write-Host ""
docker compose -f $ComposeFile ps

Write-Host ""
Write-Host "=== 压测入口 ===" -ForegroundColor Green
Write-Host "  HTTP:      http://127.0.0.1:8088"
Write-Host "  API:       http://127.0.0.1:8088/api"
Write-Host "  Health:    http://127.0.0.1:8088/api/health/live"
Write-Host "  PostgreSQL: 127.0.0.1:5433"
Write-Host "  Redis:      127.0.0.1:6380"
Write-Host ""
Write-Host "初始化（首次）:" -ForegroundColor Cyan
Write-Host "  docker compose -f $ComposeFile exec app php artisan migrate --seed --force"
Write-Host "  docker compose -f $ComposeFile exec app php artisan benchmark:env-check"
Write-Host ""
Write-Host "基线冒烟:" -ForegroundColor Cyan
Write-Host "  powershell -File scripts/benchmark-smoke.ps1"
Write-Host ""
Write-Host "详见 docs/benchmark-env-setup.md" -ForegroundColor Gray

if (-not $ready) { exit 1 }
