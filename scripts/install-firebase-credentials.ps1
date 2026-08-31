# Firebase credentials installer for HWT License
# Usage:
#   powershell -File scripts/install-firebase-credentials.ps1 `
#     -AndroidJson "$env:USERPROFILE\Downloads\google-services.json" `
#     -IosPlist "$env:USERPROFILE\Downloads\GoogleService-Info.plist" `
#     -ServiceAccount "$env:USERPROFILE\Downloads\xxx-firebase-adminsdk.json"

param(
    [string]$AndroidJson = "",
    [string]$IosPlist = "",
    [string]$ServiceAccount = "",
    [string]$ProjectId = "",
    [switch]$SkipEnv
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$ExpectedPackage = "com.huwutong.license"
$script:Passed = 0
$script:Failed = 0

function Write-Ok([string]$Msg) {
    Write-Host ("  [OK] " + $Msg) -ForegroundColor Green
    $script:Passed++
}
function Write-Fail([string]$Msg) {
    Write-Host ("  [FAIL] " + $Msg) -ForegroundColor Red
    $script:Failed++
}
function Write-Info([string]$Msg) {
    Write-Host ("  [..] " + $Msg) -ForegroundColor DarkGray
}

function Update-EnvVar([string]$Text, [string]$Key, [string]$Value) {
    $line = "$Key=$Value"
    $pattern = "(?m)^#?\s*$([regex]::Escape($Key))=.*$"
    if ($Text -match $pattern) {
        return [regex]::Replace($Text, $pattern, $line)
    }
    return ($Text.TrimEnd() + "`r`n`r`n# D-28 FCM`r`n$line`r`n")
}

Write-Host ""
Write-Host "=== HWT Firebase Credentials Installer ===" -ForegroundColor Cyan
Write-Host ("Package / Bundle ID: " + $ExpectedPackage)
Write-Host ("Root: " + $Root)
Write-Host ""

if ((-not $AndroidJson) -and (-not $IosPlist) -and (-not $ServiceAccount)) {
    Write-Host "No files specified. Create a Firebase project first:"
    Write-Host "  1. Open https://console.firebase.google.com/"
    Write-Host "  2. Add project (name: huwutong)"
    Write-Host ("  3. Add Android app, package: " + $ExpectedPackage)
    Write-Host "     Download google-services.json"
    Write-Host ("  4. Add iOS app, Bundle ID: " + $ExpectedPackage)
    Write-Host "     Download GoogleService-Info.plist"
    Write-Host "  5. Project settings - Service accounts - Generate new private key"
    Write-Host "     Download *-firebase-adminsdk-*.json"
    Write-Host ""
    Write-Host "Then run:"
    Write-Host '  powershell -File scripts/install-firebase-credentials.ps1 `'
    Write-Host '    -AndroidJson "$env:USERPROFILE\Downloads\google-services.json" `'
    Write-Host '    -IosPlist "$env:USERPROFILE\Downloads\GoogleService-Info.plist" `'
    Write-Host '    -ServiceAccount "$env:USERPROFILE\Downloads\<adminsdk>.json"'
    Write-Host ""
    Write-Host "See docs/鐪熸満璐﹀彿涓婃灦鎸囧崡.md"
    exit 1
}

# --- Android ---
Write-Host "[1/3] Android google-services.json" -ForegroundColor Yellow
if ($AndroidJson) {
    if (-not (Test-Path -LiteralPath $AndroidJson)) {
        Write-Fail ("File not found: " + $AndroidJson)
    } else {
        try {
            $gs = Get-Content -Raw -LiteralPath $AndroidJson | ConvertFrom-Json
            $pkg = $gs.client[0].client_info.android_client_info.package_name
            $pid = [string]$gs.project_info.project_id
            if ($pkg -ne $ExpectedPackage) {
                Write-Fail ("Package mismatch: expected $ExpectedPackage, got $pkg")
            } elseif ($pid -match "YOUR_|placeholder") {
                Write-Fail "Still a placeholder file; use the real Firebase download"
            } else {
                $dest = Join-Path $Root "mobile\android\app\google-services.json"
                Copy-Item -Force -LiteralPath $AndroidJson -Destination $dest
                if (-not $ProjectId) { $ProjectId = $pid }
                Write-Ok ("Installed mobile/android/app/google-services.json (project=$pid)")
            }
        } catch {
            Write-Fail ("Parse error: " + $_.Exception.Message)
        }
    }
} else {
    Write-Info "Skipped (no -AndroidJson)"
}

# --- iOS ---
Write-Host ""
Write-Host "[2/3] iOS GoogleService-Info.plist" -ForegroundColor Yellow
if ($IosPlist) {
    if (-not (Test-Path -LiteralPath $IosPlist)) {
        Write-Fail ("File not found: " + $IosPlist)
    } else {
        $plist = Get-Content -Raw -LiteralPath $IosPlist
        if ($plist -match "YOUR_PROJECT|YOUR_API_KEY|YOUR_IOS") {
            Write-Fail "Still a placeholder template; use the real Firebase download"
        } elseif ($plist -notmatch [regex]::Escape($ExpectedPackage)) {
            Write-Fail ("Bundle ID must include " + $ExpectedPackage)
        } else {
            $dest = Join-Path $Root "mobile\ios\Runner\GoogleService-Info.plist"
            Copy-Item -Force -LiteralPath $IosPlist -Destination $dest
            if ((-not $ProjectId) -and ($plist -match "<key>PROJECT_ID</key>\s*<string>([^<]+)</string>")) {
                $ProjectId = $Matches[1]
            }
            Write-Ok "Installed mobile/ios/Runner/GoogleService-Info.plist"
        }
    }
} else {
    Write-Info "Skipped (no -IosPlist); OK for Android-only push"
}

# --- Service account ---
Write-Host ""
Write-Host "[3/3] FCM service account JSON" -ForegroundColor Yellow
if ($ServiceAccount) {
    if (-not (Test-Path -LiteralPath $ServiceAccount)) {
        Write-Fail ("File not found: " + $ServiceAccount)
    } else {
        try {
            $sa = Get-Content -Raw -LiteralPath $ServiceAccount | ConvertFrom-Json
            if ($sa.type -ne "service_account") {
                Write-Fail "Not a service_account JSON"
            } elseif ((-not $sa.private_key) -or (-not $sa.project_id)) {
                Write-Fail "Missing private_key or project_id"
            } else {
                $dest = Join-Path $Root "storage\app\fcm-credentials.json"
                Copy-Item -Force -LiteralPath $ServiceAccount -Destination $dest
                if (-not $ProjectId) { $ProjectId = [string]$sa.project_id }
                Write-Ok ("Installed storage/app/fcm-credentials.json (project=$($sa.project_id))")
            }
        } catch {
            Write-Fail ("Parse error: " + $_.Exception.Message)
        }
    }
} else {
    Write-Info "Skipped (no -ServiceAccount); backend cannot send push"
}

# --- .env ---
Write-Host ""
Write-Host "[.env] FCM variables" -ForegroundColor Yellow
if ($SkipEnv) {
    Write-Info "Skipped (-SkipEnv)"
} elseif ($ProjectId -and ($ProjectId -notmatch "YOUR_")) {
    $envPath = Join-Path $Root ".env"
    if (-not (Test-Path -LiteralPath $envPath)) {
        Write-Fail ".env not found"
    } else {
        $credsRel = "storage/app/fcm-credentials.json"
        $content = Get-Content -Raw -LiteralPath $envPath
        $content = Update-EnvVar $content "FCM_PROJECT_ID" $ProjectId
        $content = Update-EnvVar $content "FCM_CREDENTIALS_PATH" $credsRel
        $content = Update-EnvVar $content "FCM_DRY_RUN" "false"
        [System.IO.File]::WriteAllText($envPath, $content)
        Write-Ok ("Wrote FCM_PROJECT_ID=" + $ProjectId)
        Write-Ok ("Wrote FCM_CREDENTIALS_PATH=" + $credsRel)
    }
} else {
    Write-Info "No project_id detected; set FCM_PROJECT_ID in .env manually"
}

Write-Host ""
$color = if ($script:Failed -eq 0) { "Green" } else { "Yellow" }
Write-Host ("Result: {0} ok, {1} failed" -f $script:Passed, $script:Failed) -ForegroundColor $color
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. php scripts/verify-mobile-credentials.php"
Write-Host "  2. php artisan config:clear"
Write-Host "  3. php artisan fcm:test-push your-email@example.com --dry-run"
Write-Host "  4. cd mobile; flutter run --dart-define=API_BASE_URL=https://88.huwutong.com/api"
Write-Host ""

if ($script:Failed -gt 0) { exit 1 }
exit 0
