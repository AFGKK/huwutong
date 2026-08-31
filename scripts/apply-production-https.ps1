# D-02: 写入生产 HTTPS / Sanctum / Reverb 环境变量
# 用法:
#   powershell -ExecutionPolicy Bypass -File scripts/apply-production-https.ps1 -Domain 88.huwutong.com
#   powershell -ExecutionPolicy Bypass -File scripts/apply-production-https.ps1 -Domain 88.huwutong.com -ExtraDomains "admin.huwutong.com,portal.huwutong.com"

param(
    [Parameter(Mandatory = $true)]
    [string]$Domain,

    [string[]]$ExtraDomains = @('www.huwutong.com', 'admin.huwutong.com', 'portal.huwutong.com'),

    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$envPath = Join-Path $Root '.env'
if (-not (Test-Path $envPath)) {
    throw ".env 不存在，请先复制 .env.example"
}

$stateful = @($Domain) + $ExtraDomains + @('localhost:5173')
$stateful = ($stateful | Select-Object -Unique) -join ','

$updates = [ordered]@{
    'APP_ENV'                   = 'production'
    'APP_DEBUG'                 = 'false'
    'APP_URL'                   = "https://$Domain"
    'FORCE_HTTPS'               = 'true'
    'TRUSTED_PROXIES'           = '*'
    'SESSION_SECURE_COOKIE'     = 'true'
    'SESSION_SAME_SITE'         = 'lax'
    'SESSION_DOMAIN'            = '.huwutong.com'
    'SANCTUM_STATEFUL_DOMAINS'  = $stateful
    'FRONTEND_URL'              = "https://$Domain"
    'REVERB_HOST'               = $Domain
    'REVERB_PORT'               = '443'
    'REVERB_SCHEME'             = 'https'
    'VITE_REVERB_HOST'          = $Domain
    'VITE_REVERB_PORT'          = '443'
    'VITE_REVERB_SCHEME'        = 'https'
}

Write-Host '========================================' -ForegroundColor Cyan
Write-Host (' D-02 HTTPS -> ' + $Domain) -ForegroundColor Cyan
Write-Host '========================================' -ForegroundColor Cyan

foreach ($key in $updates.Keys) {
    $value = $updates[$key]
    Write-Host ("  {0} = {1}" -f $key, $value) -ForegroundColor Gray
}

if ($DryRun) {
    Write-Host 'DryRun: no .env changes' -ForegroundColor Yellow
    exit 0
}

$text = Get-Content $envPath -Raw

foreach ($key in $updates.Keys) {
    $value = $updates[$key]
    $line = "$key=$value"
    if ($text -match "(?m)^$([regex]::Escape($key))=") {
        $text = [regex]::Replace($text, "(?m)^$([regex]::Escape($key))=.*$", $line)
    } else {
        $text += "`n$line"
    }
}

Set-Content -Path $envPath -Value $text.TrimEnd() -NoNewline

Write-Host '[1/2] .env updated' -ForegroundColor Green
Write-Host '[2/2] npm run build...' -ForegroundColor Yellow
npm run build | Out-Null
Write-Host '      done' -ForegroundColor Green

Write-Host ''
Write-Host '后续步骤:' -ForegroundColor Cyan
Write-Host '  1. deploy/nginx/production-https.conf' -ForegroundColor White
Write-Host '  2. certbot + nginx reload' -ForegroundColor White
Write-Host '  3. php artisan config:cache' -ForegroundColor White
Write-Host '  4. php scripts/verify-https-config.php' -ForegroundColor White
Write-Host ('  5. Echo wss://' + $Domain + '/app/...') -ForegroundColor White
