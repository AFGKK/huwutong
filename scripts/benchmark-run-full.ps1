# D-40: 5000 QPS 达标验证 — 完整压测流程
# 用法: powershell -ExecutionPolicy Bypass -File scripts/benchmark-run-full.ps1

param(
    [string]$BaseUrl = $env:BENCH_BASE_URL,
    [int]$TargetQps = 5000
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if (-not $BaseUrl) {
    # 优先 D-39 压测栈，其次本地 artisan serve
    try {
        Invoke-WebRequest -Uri "http://127.0.0.1:8088/api/health/live" -UseBasicParsing -TimeoutSec 2 | Out-Null
        $BaseUrl = "http://127.0.0.1:8088/api"
    } catch {
        $BaseUrl = "http://127.0.0.1:8000/api"
    }
}

Write-Host "=== D-40 5000 QPS 达标验证 ===" -ForegroundColor Cyan
Write-Host "Base URL: $BaseUrl"
Write-Host "Target:   $TargetQps QPS"
Write-Host ""

# 1. 若 D-39 栈可用则确保运行
if ($BaseUrl -like "*8088*") {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Write-Host "警告: Docker 未安装，8088 可能不可用" -ForegroundColor Yellow
    } else {
        $compose = "deploy/benchmark/docker-compose.benchmark.yml"
        docker compose -f $compose up -d 2>$null
        Start-Sleep -Seconds 5
    }
}

# 2. PHP 压测报告（HTTP 层 + 服务端）
$reportConcurrency = if ($BaseUrl -like "*:8000*") { 5 } else { 100 }
$reportRequests = if ($BaseUrl -like "*:8000*") { 500 } else { 5000 }
if ($BaseUrl -like "*:8000*") {
    Write-Host "注意: artisan serve 单进程，HTTP 压测使用较低并发 ($reportConcurrency)" -ForegroundColor Yellow
}
$reportArgs = @(
    "artisan", "benchmark:report",
    "--base-url=$BaseUrl",
    "--target-qps=$TargetQps",
    "--requests=$reportRequests",
    "--concurrency=$reportConcurrency",
    "--try-k6"
)
php @reportArgs
$phpExit = $LASTEXITCODE

# 3. k6 完整负载（若已安装）
$k6 = Get-Command k6 -ErrorAction SilentlyContinue
if ($k6) {
    Write-Host ""
    Write-Host "=== k6 QPS Target ===" -ForegroundColor Cyan
    k6 run -e "BASE_URL=$BaseUrl" -e "TARGET_QPS=$TargetQps" -e "DURATION=2m" `
        --summary-export="benchmarks/results/k6-qps-summary.json" `
        benchmarks/k6/scripts/qps-target.js

    if ($env:TOKEN) {
        Write-Host ""
        Write-Host "=== k6 Mixed Load (d40) ===" -ForegroundColor Cyan
        k6 run -e "BASE_URL=$BaseUrl" -e "TOKEN=$env:TOKEN" -e "STAGE=d40" `
            --summary-export="benchmarks/results/k6-load-summary.json" `
            benchmarks/k6/scripts/load-test.js
    }
} else {
    Write-Host "k6 未安装，跳过 k6 阶段。安装: winget install k6" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "归档报告: benchmarks/results/benchmark-result.json" -ForegroundColor Green

if ($phpExit -ne 0) {
    Write-Host "未达 $TargetQps QPS 基线。请使用 D-39 栈 (scripts/benchmark-up.ps1) 后重试。" -ForegroundColor Yellow
    exit 1
}

Write-Host "D-40 验证完成 ✅" -ForegroundColor Green
