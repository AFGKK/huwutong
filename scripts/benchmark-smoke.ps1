# D-39: HTTP 基线冒烟（目标 >1000 QPS on /api/health/live）
# 用法: powershell -ExecutionPolicy Bypass -File scripts/benchmark-smoke.ps1

param(
    [string]$Url = "http://127.0.0.1:8088/api/health/live",
    [int]$Requests = 2000,
    [int]$Concurrency = 50
)

$ErrorActionPreference = "Stop"

Write-Host "=== D-39 基线冒烟 ===" -ForegroundColor Cyan
Write-Host "URL: $Url"
Write-Host "Requests: $Requests  Concurrency: $Concurrency"
Write-Host ""

# 预热
try {
    Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5 | Out-Null
} catch {
    Write-Host "无法连接 $Url — 请先运行 scripts/benchmark-up.ps1" -ForegroundColor Red
    exit 1
}

$jobs = @()
$batchSize = $Concurrency
$batches = [math]::Ceiling($Requests / $batchSize)
$sw = [System.Diagnostics.Stopwatch]::StartNew()

for ($b = 0; $b -lt $batches; $b++) {
    $count = [Math]::Min($batchSize, $Requests - ($b * $batchSize))
    $jobs += Start-Job -ScriptBlock {
        param($u, $n)
        $ok = 0
        $fail = 0
        for ($i = 0; $i -lt $n; $i++) {
            try {
                $r = Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 10
                if ($r.StatusCode -eq 200) { $ok++ } else { $fail++ }
            } catch { $fail++ }
        }
        return @{ ok = $ok; fail = $fail }
    } -ArgumentList $Url, $count
}

$results = $jobs | Wait-Job | Receive-Job
$jobs | Remove-Job -Force
$sw.Stop()

$totalOk = ($results | ForEach-Object { $_.ok } | Measure-Object -Sum).Sum
$totalFail = ($results | ForEach-Object { $_.fail } | Measure-Object -Sum).Sum
$elapsed = $sw.Elapsed.TotalSeconds
$qps = if ($elapsed -gt 0) { [math]::Round($totalOk / $elapsed, 1) } else { 0 }
$baseline = 1000
$passed = $qps -ge $baseline

Write-Host "完成: $($totalOk + $totalFail) 请求, 成功 $totalOk, 失败 $totalFail" -ForegroundColor $(if ($totalFail -eq 0) { "Green" } else { "Yellow" })
Write-Host "耗时: $([math]::Round($elapsed, 2))s"
Write-Host "QPS:  $qps (基线目标 >= $baseline)" -ForegroundColor $(if ($passed) { "Green" } else { "Yellow" })

$report = @{
    timestamp = (Get-Date -Format "o")
    url = $Url
    requests = $Requests
    concurrency = $Concurrency
    success = $totalOk
    failed = $totalFail
    duration_sec = [math]::Round($elapsed, 3)
    qps = $qps
    baseline_qps = $baseline
    passed = $passed
} | ConvertTo-Json

$outDir = Join-Path (Split-Path -Parent $PSScriptRoot) "benchmarks/results"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$outFile = Join-Path $outDir "baseline-smoke-$(Get-Date -Format 'yyyyMMdd-HHmmss').json"
Set-Content -Path $outFile -Value $report -Encoding UTF8
Write-Host "报告: $outFile"

if (-not $passed) {
    Write-Host "未达基线，可调整 PHP-FPM pm.max_children 或增加并发后重试。" -ForegroundColor Yellow
    exit 1
}

Write-Host "基线达标 ✅" -ForegroundColor Green
