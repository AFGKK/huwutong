# 互物通 — 从运维机远程探活生产站点（P0）
# 用法:
#   powershell -ExecutionPolicy Bypass -File scripts/verify-production.ps1
#   powershell -ExecutionPolicy Bypass -File scripts/verify-production.ps1 -BaseUrl https://88.huwutong.com -ReverbAppKey YOUR_KEY

param(
    [string]$BaseUrl = "https://88.huwutong.com",
    [string]$ReverbAppKey = ""
)

$ErrorActionPreference = "Continue"
$passed = 0
$failed = 0

function Write-Check([string]$Label, [bool]$Ok, [string]$Detail = "") {
    $script:passed += [int]$Ok
    $script:failed += [int](-not $Ok)
    $mark = if ($Ok) { "[✓]" } else { "[✗]" }
    $color = if ($Ok) { "Green" } else { "Red" }
    $msg = "$mark $Label"
    if ($Detail) { $msg += " — $Detail" }
    Write-Host $msg -ForegroundColor $color
}

Write-Host ""
Write-Host "=== 互物通 P0 生产探活 ===" -ForegroundColor Cyan
Write-Host "BaseUrl: $BaseUrl"
Write-Host ""

# HTTPS 首页 / 健康
try {
    $r = Invoke-WebRequest -Uri "$BaseUrl/api/health/ready" -UseBasicParsing -TimeoutSec 15
    Write-Check "API health/ready" ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) "HTTP $($r.StatusCode)"
} catch {
    Write-Check "API health/ready" $false $_.Exception.Message
}

try {
    $r = Invoke-WebRequest -Uri "$BaseUrl/api/health/live" -UseBasicParsing -TimeoutSec 15
    Write-Check "API health/live" ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) "HTTP $($r.StatusCode)"
} catch {
    Write-Check "API health/live" $false $_.Exception.Message
}

try {
    $r = Invoke-WebRequest -Uri $BaseUrl -UseBasicParsing -TimeoutSec 15 -MaximumRedirection 5
    Write-Check "首页可达" ($r.StatusCode -ge 200 -and $r.StatusCode -lt 400) "HTTP $($r.StatusCode)"
} catch {
    Write-Check "首页可达" $false $_.Exception.Message
}

# HTTP → HTTPS（若 BaseUrl 为 https，探测对应 http）
if ($BaseUrl -match '^https://(.+)$') {
    $httpUrl = "http://$($Matches[1])"
    try {
        $r = Invoke-WebRequest -Uri $httpUrl -UseBasicParsing -TimeoutSec 10 -MaximumRedirection 0 -ErrorAction SilentlyContinue
        Write-Check "HTTP 跳转 HTTPS" ($r.StatusCode -in 301, 302, 308) "HTTP $($r.StatusCode)"
    } catch {
        $resp = $_.Exception.Response
        if ($resp -and [int]$resp.StatusCode -in 301, 302, 308) {
            Write-Check "HTTP 跳转 HTTPS" $true "HTTP $([int]$resp.StatusCode)"
        } else {
            Write-Check "HTTP 跳转 HTTPS" $false $_.Exception.Message
        }
    }
}

# Sanctum CSRF
try {
    $session = $null
    $r = Invoke-WebRequest -Uri "$BaseUrl/sanctum/csrf-cookie" -UseBasicParsing -TimeoutSec 15 -SessionVariable session
    $hasCookie = $false
    if ($session -and $session.Cookies) {
        $hasCookie = ($session.Cookies.Count -gt 0)
    }
    Write-Check "Sanctum CSRF Cookie" ($r.StatusCode -lt 500) "HTTP $($r.StatusCode); cookies=$hasCookie"
} catch {
    Write-Check "Sanctum CSRF Cookie" $false $_.Exception.Message
}

# WebSocket（可选）
if ($ReverbAppKey) {
    try {
        $ws = New-Object System.Net.WebSockets.ClientWebSocket
        $cts = New-Object System.Threading.CancellationTokenSource
        $cts.CancelAfter(5000)
        $uri = New-Object System.Uri(($BaseUrl -replace '^https://', 'wss://') + "/app/$ReverbAppKey")
        $task = $ws.ConnectAsync($uri, $cts.Token)
        $task.Wait(6000) | Out-Null
        Write-Check "WebSocket wss /app" ($ws.State.ToString() -eq 'Open') $ws.State.ToString()
        $ws.Dispose()
    } catch {
        Write-Check "WebSocket wss /app" $false $_.Exception.Message
    }
} else {
    Write-Host "[-] WebSocket 跳过（传入 -ReverbAppKey 可测）" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "结果: $passed 通过, $failed 失败" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Yellow" })
Write-Host "服务器侧请再跑: php scripts/verify-production.php" -ForegroundColor DarkGray
Write-Host ""

if ($failed -gt 0) { exit 1 }
exit 0
