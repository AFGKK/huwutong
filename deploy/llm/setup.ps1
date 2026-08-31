# ===================================================
# 本地大模型部署脚本 (Windows PowerShell)
# M3-49 Local LLM Deployment
#
# 使用方式:
#   .\deploy\llm\setup.ps1 ollama       # 部署 Ollama
#   .\deploy\llm\setup.ps1 vllm         # 部署 vLLM
#   .\deploy\llm\setup.ps1 models       # 下载推荐模型
#   .\deploy\llm\setup.ps1 status       # 查看运行状态
# ===================================================

param(
    [Parameter(Position=0)]
    [ValidateSet('ollama', 'vllm', 'models', 'status')]
    [string]$Command = 'status'
)

$SCRIPT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$INFRA_DIR = Split-Path -Parent (Split-Path -Parent $SCRIPT_DIR)

function Write-Info { Write-Host "[INFO] $args" -ForegroundColor Green }
function Write-Error { Write-Host "[ERROR] $args" -ForegroundColor Red }

# 检查 Docker 是否可用
function Test-DockerAvailable {
    try {
        $null = docker --version 2>&1 | Out-String
        return $true
    } catch {
        return $false
    }
}

# ===================================================
# Ollama 部署
# ===================================================
function Deploy-Ollama {
    Write-Info "正在部署 Ollama (CPU/GPU)..."
    
    if (-not (Test-DockerAvailable)) {
        Write-Error "Docker 未安装，请先安装 Docker Desktop: https://www.docker.com/products/docker-desktop/"
        return
    }

    Write-Info "启动 Ollama + Open WebUI..."
    docker compose -f "$SCRIPT_DIR/docker-compose.ollama.yml" up -d
    
    if ($LASTEXITCODE -eq 0) {
        Write-Info "Ollama 已启动！"
        Write-Info "  API: http://localhost:11434"
        Write-Info "  WebUI: http://localhost:3000"
        Write-Info ""
        Write-Info "运行以下命令下载推荐模型："
        Write-Info "  .\deploy\llm\setup.ps1 models"
    } else {
        Write-Error "启动失败，请检查 Docker 日志"
    }
}

# ===================================================
# vLLM 部署
# ===================================================
function Deploy-Vllm {
    Write-Info "正在部署 vLLM (GPU 推荐，需要 NVIDIA GPU)..."
    
    if (-not (Test-DockerAvailable)) {
        Write-Error "Docker 未安装，请先安装 Docker Desktop"
        return
    }

    Write-Info "启动 vLLM..."
    docker compose -f "$SCRIPT_DIR/docker-compose.vllm.yml" up -d

    if ($LASTEXITCODE -eq 0) {
        Write-Info "vLLM 已启动！"
        Write-Info "  API: http://localhost:8000"
        Write-Info "  OpenAI 兼容接口: http://localhost:8000/v1"
    } else {
        Write-Error "启动失败，请检查 Docker 日志"
    }
}

# ===================================================
# 下载推荐模型
# ===================================================
function Download-Models {
    Write-Info "正在下载推荐模型到 Ollama..."

    # 检查 Ollama 是否运行
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -Method GET -TimeoutSec 5 -ErrorAction Stop
        Write-Info "Ollama API 连接成功"
    } catch {
        Write-Error "无法连接 Ollama API (http://localhost:11434)"
        Write-Error "请先运行 .\deploy\llm\setup.ps1 ollama 启动 Ollama"
        return
    }

    $models = @(
        @{name = "qwen2.5:7b"; desc = "通义千问 7B (推荐，中文优秀)"},
        @{name = "qwen2.5:1.5b"; desc = "通义千问 1.5B (轻量，适合 CPU)"},
        @{name = "nomic-embed-text"; desc = "文本嵌入模型 (RAG 知识库)"}
    )

    foreach ($m in $models) {
        Write-Info "下载 $($m.name) - $($m.desc)..."
        Write-Info "  运行: docker exec hwt-ollama ollama pull $($m.name)"
        
        $job = Start-Job -ScriptBlock {
            param($modelName)
            docker exec hwt-ollama ollama pull $modelName 2>&1
        } -ArgumentList $m.name

        # 等待完成（最多 30 分钟）
        $timeout = 1800  # 30 minutes in seconds
        $elapsed = 0
        while ($job.State -eq 'Running' -and $elapsed -lt $timeout) {
            Start-Sleep -Seconds 5
            $elapsed += 5
            Write-Host "   正在下载 $($m.name)... ($elapsed 秒)" -ForegroundColor Yellow
        }

        if ($job.State -eq 'Running') {
            Stop-Job $job
            Write-Error "下载 $($m.name) 超时，请手动运行: docker exec hwt-ollama ollama pull $($m.name)"
        } else {
            $result = Receive-Job $job
            Write-Info "模型 $($m.name) 下载完成"
        }
        Remove-Job $job -ErrorAction SilentlyContinue
    }

    Write-Info "所有模型已下载完成！"
    Write-Info "在 .env 文件中配置默认模型："
    Write-Info "  OLLAMA_DEFAULT_MODEL=qwen2.5:7b"
}

# ===================================================
# Windows 原生安装（环境无 Docker 时的备用方案）
# ===================================================
function Install-OllamaNative {
    Write-Info "Windows 原生 Ollama 安装检查"
    Write-Info "================================"
    
    $ollamaPath = "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe"
    $installed = Test-Path $ollamaPath
    
    if ($installed) {
        Write-Info "Ollama 已安装: $ollamaPath"
        try {
            $resp = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -TimeoutSec 3 -ErrorAction Stop
            Write-Info "Ollama 服务: ✅ 运行中"
        } catch {
            Write-Warning "Ollama 服务: ⚠️ 未运行 — 请手动启动 Ollama 应用程序"
        }
    } else {
        Write-Info "Ollama 未安装，有两种方式:"
        Write-Info "  [方式一] 原生安装: 下载 https://ollama.com/download/Windows → 运行 OllamaSetup.exe"
        Write-Info "    安装后: ollama pull qwen2.5:7b && ollama pull nomic-embed-text"
        Write-Info "  [方式二] Docker: docker compose -f deploy/llm/docker-compose.ollama.yml up -d"
    }
}

# ===================================================
# 查看状态
# ===================================================
function Show-Status {
    Write-Info "本地大模型运行状态"
    Write-Info "======================"

    # 检查 Ollama
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -Method GET -TimeoutSec 3 -ErrorAction Stop
        $data = $response.Content | ConvertFrom-Json
        $modelCount = ($data.models | Measure-Object).Count
        Write-Info "Ollama: ✅ 运行中 (http://localhost:11434)"
        Write-Info "  已下载模型: $modelCount 个"
        if ($modelCount -gt 0) {
            foreach ($m in $data.models) {
                Write-Info "    - $($m.name)"
            }
        }
    } catch {
        Write-Error "Ollama: ❌ 未运行 (http://localhost:11434)"
        Write-Info "  启动命令: .\deploy\llm\setup.ps1 ollama"
    }

    # 检查 vLLM
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/v1/models" -Method GET -TimeoutSec 3 -ErrorAction Stop
        Write-Info "vLLM: ✅ 运行中 (http://localhost:8000)"
    } catch {
        Write-Error "vLLM: ❌ 未运行 (http://localhost:8000)"
    }

    # 检查 Docker 容器
    if (Test-DockerAvailable) {
        Write-Info ""
        Write-Info "Docker 容器状态:"
        docker ps --filter "name=ollama" --filter "name=vllm" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>&1
    }
    
    Write-Info ""
    Write-Info "======================"
    Write-Info "配置提示:"
    Write-Info "  在 .env 中设置 LOCAL_LLM_ENABLED=true 启用本地 LLM"
    Write-Info "  在 .env 中设置 OLLAMA_API_BASE=http://localhost:11434"
}

# ===================================================
# 主入口
# ===================================================
switch ($Command) {
    'ollama' { Deploy-Ollama }
    'vllm' { Deploy-Vllm }
    'models' { Download-Models }
    'native' { Install-OllamaNative }
    'status' { Show-Status }
    default { Show-Status }
}
