# 自动安装 pgvector for PostgreSQL 16 (Windows)
# 需要管理员权限写入 Program Files\PostgreSQL\16
# 用法: 以管理员身份运行 PowerShell
#   Set-ExecutionPolicy Bypass -Scope Process; .\scripts\install-pgvector-windows.ps1

$ErrorActionPreference = 'Stop'
$tag = '0.8.3_16.14'
$zipUrl = "https://github.com/andreiramani/pgvector_pgsql_windows/releases/download/$tag/vector.v0.8.3-pg16.zip"
$pgRoot = 'C:\Program Files\PostgreSQL\16'
$tempDir = Join-Path $env:TEMP "pgvector-$tag"
$zipFile = Join-Path $env:TEMP "pgvector-$tag.zip"

Write-Host "=== 安装 pgvector $tag for PostgreSQL 16 ===" -ForegroundColor Cyan

if (-not (Test-Path $pgRoot)) {
    Write-Error "未找到 PostgreSQL 16: $pgRoot"
}

Write-Host "下载: $zipUrl"
Invoke-WebRequest -Uri $zipUrl -OutFile $zipFile -UseBasicParsing

if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir | Out-Null
Expand-Archive -Path $zipFile -DestinationPath $tempDir -Force

# 查找解压后的文件结构
$dll = Get-ChildItem -Path $tempDir -Recurse -Filter 'vector.dll' | Select-Object -First 1
$control = Get-ChildItem -Path $tempDir -Recurse -Filter 'vector.control' | Select-Object -First 1
$sqlFiles = Get-ChildItem -Path $tempDir -Recurse -Filter 'vector--*.sql'

if (-not $dll -or -not $control) {
    Write-Error "ZIP 包结构异常，未找到 vector.dll 或 vector.control"
}

$libDir = Join-Path $pgRoot 'lib'
$extDir = Join-Path $pgRoot 'share\extension'

Write-Host "复制 vector.dll -> $libDir"
Copy-Item $dll.FullName (Join-Path $libDir 'vector.dll') -Force

Write-Host "复制 extension 文件 -> $extDir"
Copy-Item $control.FullName (Join-Path $extDir 'vector.control') -Force
foreach ($sql in $sqlFiles) {
    Copy-Item $sql.FullName (Join-Path $extDir $sql.Name) -Force
}

# 验证扩展可用
$env:PGPASSWORD = ''  # 使用 trust 或 .pgpass
$psql = Join-Path $pgRoot 'bin\psql.exe'
& $psql -U postgres -d huwutong -c "CREATE EXTENSION IF NOT EXISTS vector;" 2>&1
& $psql -U postgres -d huwutong -c "SELECT extname, extversion FROM pg_extension WHERE extname='vector';" 2>&1

Write-Host ""
Write-Host "完成。请运行: php scripts/install-pgvector.php 验证" -ForegroundColor Green
