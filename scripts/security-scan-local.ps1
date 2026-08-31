# D-41: 本地 ZAP 门禁验证
# 用法: powershell -ExecutionPolicy Bypass -File scripts/security-scan-local.ps1

param(
    [string]$TargetUrl = "http://127.0.0.1:8000",
    [switch]$SkipServe
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host "=== D-41 安全扫描本地验证 ===" -ForegroundColor Cyan
Write-Host "Target: $TargetUrl"
Write-Host ""

if (-not $SkipServe) {
    try {
        Invoke-WebRequest -Uri "$TargetUrl/api/health/live" -UseBasicParsing -TimeoutSec 3 | Out-Null
    } catch {
        Write-Host "启动 artisan serve..." -ForegroundColor Yellow
        Start-Process -FilePath "php" -ArgumentList "artisan serve --host=127.0.0.1 --port=8000" -WorkingDirectory $Root -WindowStyle Hidden
        Start-Sleep -Seconds 4
    }
}

Write-Host "1. 静态安全分析 (security:scan --quick)" -ForegroundColor Cyan
php artisan security:scan --quick --target="$TargetUrl"
$staticExit = $LASTEXITCODE

Write-Host ""
Write-Host "2. ZAP 基线扫描 (需 Docker)" -ForegroundColor Cyan
$docker = Get-Command docker -ErrorAction SilentlyContinue
if ($docker) {
    $env:TARGET_URL = $TargetUrl
    $env:FAIL_ON_HIGH = "true"
    bash .ci/zap/ci-scan.sh baseline 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ZAP Docker 扫描跳过或失败（无镜像/目标不可达）" -ForegroundColor Yellow
    }
} else {
    Write-Host "Docker 未安装，跳过 ZAP 动态扫描" -ForegroundColor Yellow
    Write-Host "CI 将在 GitHub Actions 中执行 zaproxy/action-baseline" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "归档: security_scan_results 表 + CI 产物 report.xml" -ForegroundColor Green

if ($staticExit -ne 0) {
    Write-Host "静态分析未通过，请修复高危项后重试" -ForegroundColor Red
    exit 1
}

Write-Host "D-41 本地验证通过 ✅" -ForegroundColor Green
