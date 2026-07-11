# 局域网真机联调：启动 HTTP + Reverb WebSocket
# 用法: powershell -ExecutionPolicy Bypass -File scripts/start-dev-lan.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Get-LanIp {
    $ip = Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
        Select-Object -First 1 -ExpandProperty IPAddress
    if (-not $ip) { throw "未检测到局域网 IPv4 地址" }
    return $ip
}

$lanIp = Get-LanIp
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " 互物通 局域网联调 (LAN: $lanIp)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# 更新 .env 中的 Reverb / Sanctum 局域网配置
$envPath = Join-Path $Root ".env"
$envText = Get-Content $envPath -Raw
$envText = $envText -replace 'REVERB_HOST=.*', "REVERB_HOST=$lanIp"
$envText = $envText -replace 'VITE_REVERB_HOST=.*', "VITE_REVERB_HOST=$lanIp"
if ($envText -notmatch '192\.168\.\d+\.\d+:8000') {
    $envText = $envText -replace '(SANCTUM_STATEFUL_DOMAINS=.*)', "`$1,$lanIp`:8000"
}
Set-Content $envPath $envText -NoNewline

Write-Host "[1/3] 已写入 .env: REVERB_HOST / VITE_REVERB_HOST = $lanIp" -ForegroundColor Green

Write-Host "[2/3] 重新构建前端 (注入 VITE_REVERB_*)..." -ForegroundColor Yellow
npm run build | Out-Null
Write-Host "      构建完成" -ForegroundColor Green

Write-Host "[3/3] 启动服务..." -ForegroundColor Yellow
Write-Host ""
Write-Host "  管理后台:  http://${lanIp}:8000/build/login" -ForegroundColor White
Write-Host "  IM 私信:   http://${lanIp}:8000/build/user-chat" -ForegroundColor White
Write-Host "  WebSocket: ws://${lanIp}:8080" -ForegroundColor White
Write-Host ""
Write-Host "  测试账号 A: admin@huwutong.com / admin123" -ForegroundColor Gray
Write-Host "  测试账号 B: demo@huwutong.com / demo123" -ForegroundColor Gray
Write-Host ""
Write-Host "  真机 WebRTC: 两台设备分别登录 A/B，进入私信会话后点语音/视频通话" -ForegroundColor Gray
Write-Host "  iOS Safari 需 HTTPS 才能使用麦克风，建议 Android Chrome 或 PC+手机组合" -ForegroundColor Yellow
Write-Host ""
Write-Host "按 Ctrl+C 停止全部服务" -ForegroundColor DarkGray

# 结束占用端口的旧进程
foreach ($port in @(8000, 8080)) {
    $conn = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($conn) {
        Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
    }
}

$reverbJob = Start-Job -ScriptBlock {
    Set-Location $using:Root
    php artisan reverb:start --host=0.0.0.0 --port=8080 2>&1
}

Start-Sleep -Seconds 2
php artisan serve --host=0.0.0.0 --port=8000

Stop-Job $reverbJob -ErrorAction SilentlyContinue
Remove-Job $reverbJob -Force -ErrorAction SilentlyContinue
