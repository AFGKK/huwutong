<#
.DESCRIPTION
    D-20: 互物通 Docker 全栈一键启动 (PowerShell)
.EXAMPLE
    .\docker-start.ps1 up         # 启动环境
    .\docker-start.ps1 down       # 关闭环境
    .\docker-start.ps1 status     # 查看状态
    .\docker-start.ps1 fresh      # 重建数据库
    .\docker-start.ps1 logs       # 跟踪日志
#>

param(
    [ValidateSet('up','down','restart','status','fresh','logs')]
    [string]$Action = 'up'
)

$ComposeFile = "docker-compose.yml"

switch ($Action) {
    'up' {
        Write-Host "[D-20] 正在启动互物通全栈开发环境..." -ForegroundColor Cyan
        docker compose -f $ComposeFile up -d
        if ($LASTEXITCODE -eq 0) {
            Write-Host "`n[OK] 全栈已启动！" -ForegroundColor Green
            Write-Host "`n  网站:     http://localhost" -ForegroundColor White
            Write-Host "  邮件:     http://localhost:8025" -ForegroundColor White
            Write-Host "  Meilisearch: http://localhost:7700" -ForegroundColor White
            Write-Host "  Ollama:   http://localhost:11434" -ForegroundColor White
            Write-Host "`n  运行 .\docker-start.ps1 logs 查看日志" -ForegroundColor Yellow
        } else {
            Write-Host "[FAIL] 启动失败" -ForegroundColor Red
        }
        break
    }
    'down' {
        Write-Host "[D-20] 正在关闭互物通全栈开发环境..." -ForegroundColor Cyan
        docker compose -f $ComposeFile down
        Write-Host "[OK] 全栈已关闭" -ForegroundColor Green
        break
    }
    'restart' {
        Write-Host "[D-20] 正在重启互物通全栈..." -ForegroundColor Cyan
        docker compose -f $ComposeFile restart
        break
    }
    'status' {
        docker compose -f $ComposeFile ps
        break
    }
    'fresh' {
        Write-Host "[D-20] 正在重建数据库并填充种子数据..." -ForegroundColor Cyan
        docker compose -f $ComposeFile run --rm app php artisan migrate:fresh --seed --force
        break
    }
    'logs' {
        docker compose -f $ComposeFile logs -f
        break
    }
}
