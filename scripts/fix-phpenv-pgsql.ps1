# 修复 phpEnv Web PHP 无法加载 pdo_pgsql 的问题
# 原因: extension_dir 使用相对路径 "ext"，FastCGI 工作目录为网站 public/ 导致扩展找不到
# 用法: 以管理员身份运行 PowerShell，执行 .\scripts\fix-phpenv-pgsql.ps1

$phpIni = 'D:\phpEnv\php\php-8.3\php.ini'
$extDir = 'D:\phpEnv\php\php-8.3\ext'
$pgBin  = 'C:\Program Files\PostgreSQL\16\bin'

Write-Host "=== phpEnv PostgreSQL 扩展修复 ===" -ForegroundColor Cyan

if (-not (Test-Path $phpIni)) {
    Write-Error "未找到 php.ini: $phpIni"
    exit 1
}

$content = Get-Content $phpIni -Raw
if ($content -match 'extension_dir\s*=\s*"ext"') {
    $content = $content -replace 'extension_dir\s*=\s*"ext"', 'extension_dir = "D:\phpEnv\php\php-8.3\ext"'
    Set-Content -Path $phpIni -Value $content -NoNewline
    Write-Host "已更新 extension_dir 为绝对路径" -ForegroundColor Green
} else {
    Write-Host "extension_dir 已是绝对路径或已修改，跳过" -ForegroundColor Yellow
}

# 确保 libpq.dll 可被 PHP 加载
$libpq = Join-Path $pgBin 'libpq.dll'
if (Test-Path $libpq) {
    Copy-Item $libpq (Join-Path $extDir 'libpq.dll') -Force -ErrorAction SilentlyContinue
    Copy-Item $libpq 'D:\phpEnv\php\php-8.3\libpq.dll' -Force -ErrorAction SilentlyContinue
    Write-Host "已复制 libpq.dll 到 PHP 目录" -ForegroundColor Green
}

# 验证 php-cgi 配置（新进程）
& 'D:\phpEnv\php\php-8.3\php-cgi.exe' -i 2>&1 | Select-String -Pattern 'extension_dir|pdo_pgsql' | ForEach-Object { $_.Line.Trim() }

Write-Host ""
Write-Host "请重启 phpEnv（停止后重新启动 Nginx/PHP 服务）使 FastCGI 进程重新加载 php.ini" -ForegroundColor Yellow
Write-Host "重启后验证: curl http://88.huwutong.com/phpinfo-pgsql-check.php" -ForegroundColor Yellow
