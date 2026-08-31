# D-19: 删除并重建 Meilisearch 全部索引后全量同步
# 用法: powershell -ExecutionPolicy Bypass -File scripts/rebuild-meilisearch.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

Write-Host "=== Meilisearch 重建 ===" -ForegroundColor Cyan

$startScript = Join-Path $Root "scripts\start-meilisearch.ps1"
if (Test-Path $startScript) {
    Write-Host "检查/启动 Meilisearch..." -ForegroundColor Yellow
    & powershell -ExecutionPolicy Bypass -File $startScript
}

php artisan meilisearch:sync --rebuild
if ($LASTEXITCODE -ne 0) {
    Write-Host "重建失败，请检查 Meilisearch 是否运行。" -ForegroundColor Red
    exit 1
}

Write-Host "完成。" -ForegroundColor Green
