# Meilisearch 本地启动（Windows，无需 Docker）
# 用法: powershell -ExecutionPolicy Bypass -File scripts/start-meilisearch.ps1

$meiliDir = "$env:USERPROFILE\.meilisearch"
$exe = Join-Path $meiliDir "meilisearch.exe"
$logFile = Join-Path $meiliDir "meilisearch.log"
$masterKey = if ($env:MEILISEARCH_API_KEY) { $env:MEILISEARCH_API_KEY } else { "huwutong-dev-master-key" }

if (-not (Test-Path $exe)) {
    Write-Host "未找到 $exe"
    Write-Host "请从 https://github.com/meilisearch/meilisearch/releases 下载 Windows 版并解压到 $meiliDir"
    exit 1
}

$existing = Get-Process -Name "meilisearch" -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Meilisearch 已在运行, PID: $($existing.Id)"
    exit 0
}

New-Item -ItemType Directory -Force -Path $meiliDir | Out-Null

$proc = Start-Process -FilePath $exe -ArgumentList @(
    "--master-key", $masterKey,
    "--http-addr", "127.0.0.1:7700",
    "--env", "development"
) -WindowStyle Hidden -PassThru -RedirectStandardOutput $logFile -RedirectStandardError "${logFile}.err"

$proc.Id | Out-File -FilePath (Join-Path $meiliDir "pid.txt") -Force
Write-Host "Meilisearch 已启动, PID: $($proc.Id)"

Start-Sleep 2
try {
    $health = Invoke-RestMethod -Uri "http://127.0.0.1:7700/health"
    Write-Host "状态: $($health.status)"
} catch {
    Write-Host "等待 Meilisearch 就绪，日志: $logFile"
}
