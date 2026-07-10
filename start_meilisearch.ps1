# Meilisearch 启动脚本
$meiliDir = "$env:USERPROFILE\.meilisearch"
$logFile = "$meiliDir\meilisearch.log"

# 检查是否已在运行
$existing = Get-Process -Name "meilisearch" -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Meilisearch 已在运行, PID: $($existing.Id)"
    exit 0
}

# 启动 Meilisearch
$proc = Start-Process -FilePath "$meiliDir\meilisearch.exe" -ArgumentList @(
    "--master-key", "huwutong-dev-master-key",
    "--http-addr", "127.0.0.1:7700",
    "--env", "development"
) -WindowStyle Hidden -PassThru -RedirectStandardOutput $logFile -RedirectStandardError "${logFile}.err"

$proc.Id | Out-File -FilePath "$meiliDir\pid.txt" -Force
Write-Host "Meilisearch 已启动, PID: $($proc.Id)"

# 等待就绪
Start-Sleep 2
try {
    $health = Invoke-RestMethod -Uri "http://127.0.0.1:7700/health"
    Write-Host "状态: $($health.status)"
}
catch {
    Write-Host "等待 Meilisearch 就绪..."
}
