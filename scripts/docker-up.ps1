# D-20: Docker Compose 一键启动开发栈
# 用法: powershell -ExecutionPolicy Bypass -File scripts/docker-up.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host "=== HWT Docker 开发栈 (D-20) ===" -ForegroundColor Cyan

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "未检测到 Docker，请先安装 Docker Desktop。" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path ".env")) {
    Write-Host "创建 .env ..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    php artisan key:generate --force
}

Write-Host "启动服务: postgres, redis, meilisearch, ollama, mailpit, reverb, queue" -ForegroundColor Yellow
docker compose up -d --build

Write-Host ""
Write-Host "等待健康检查..." -ForegroundColor Yellow
Start-Sleep -Seconds 8
docker compose ps

Write-Host ""
Write-Host "=== 服务地址（宿主机 PHP 使用 127.0.0.1）===" -ForegroundColor Green
Write-Host "  PostgreSQL:   127.0.0.1:5432"
Write-Host "  Redis:        127.0.0.1:6379"
Write-Host "  Meilisearch:  http://127.0.0.1:7700"
Write-Host "  Ollama:       http://127.0.0.1:11434"
Write-Host "  Reverb WS:    ws://127.0.0.1:8080"
Write-Host "  Mailpit UI:   http://127.0.0.1:8025"
Write-Host ""
Write-Host "后续步骤:" -ForegroundColor Cyan
Write-Host "  php artisan migrate --seed"
Write-Host "  php artisan meilisearch:sync"
Write-Host "  php artisan serve --host=0.0.0.0 --port=8000"
Write-Host "  npm run dev"
Write-Host ""
Write-Host "详见 docs/docker-compose-dev.md" -ForegroundColor Gray
