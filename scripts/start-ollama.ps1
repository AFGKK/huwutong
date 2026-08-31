# Ollama 本地启动（Windows 原生，无需 Docker）
# 用法: powershell -ExecutionPolicy Bypass -File scripts/start-ollama.ps1
#
# 若未安装，请从 https://ollama.com/download 下载 Windows 版

$apiUrl = if ($env:OLLAMA_API_BASE) { $env:OLLAMA_API_BASE } else { "http://127.0.0.1:11434" }

try {
    $health = Invoke-RestMethod -Uri "$apiUrl/api/tags" -TimeoutSec 3
    Write-Host "Ollama 已在运行 ($apiUrl)"
    Write-Host "已安装模型: $(($health.models | Measure-Object).Count) 个"
    exit 0
} catch {
    Write-Host "Ollama API 未响应，尝试启动..."
}

$ollamaExe = $null
foreach ($path in @(
    "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe",
    "$env:ProgramFiles\Ollama\ollama.exe",
    (Get-Command ollama -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
)) {
    if ($path -and (Test-Path $path)) {
        $ollamaExe = $path
        break
    }
}

if (-not $ollamaExe) {
    Write-Host "未找到 ollama.exe"
    Write-Host "请安装 Ollama: https://ollama.com/download"
    Write-Host "或使用 Docker: powershell -File deploy/llm/setup.ps1 ollama"
    exit 1
}

$existing = Get-Process -Name "ollama" -ErrorAction SilentlyContinue
if (-not $existing) {
    Start-Process -FilePath $ollamaExe -ArgumentList "serve" -WindowStyle Hidden
    Start-Sleep 3
}

try {
    $health = Invoke-RestMethod -Uri "$apiUrl/api/tags" -TimeoutSec 10
    Write-Host "Ollama 已启动: $apiUrl"
    Write-Host "拉取模型: php artisan ollama:setup --pull"
} catch {
    Write-Host "Ollama 启动中，请稍后运行: php artisan ollama:setup --status"
}
