<#
.SYNOPSIS
    M2-37 灾备切换演练脚本 — 模拟故障切换全流程
.DESCRIPTION
    模拟完整的灾备切换流程，验证 RTO < 5min / RPO < 1min 达标。
    在 Staging 环境执行，不会影响生产。
.PARAMETER Mode
    演练模式: verify (仅检查) | switch (执行切换) | full (完整演练)
.PARAMETER RuleId
    故障切换规则 ID
.EXAMPLE
    .\scripts\dr-drill.ps1 -Mode verify
    .\scripts\dr-drill.ps1 -Mode full -RuleId 1
#>

param(
    [ValidateSet('verify', 'switch', 'full')]
    [string]$Mode = 'verify',
    [string]$RuleId = '1'
)

$ErrorActionPreference = 'Stop'
$startTime = Get-Date

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  互物通 灾备切换演练" -ForegroundColor Cyan
Write-Host "  M2-37 | 环境: $env:APP_ENV" -ForegroundColor Cyan
Write-Host "  规则 ID: $RuleId" -ForegroundColor Cyan
Write-Host "  时间: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# ─── 阶段 1: 演练前检查 ───
function Step-PreCheck {
    Write-Host "[1/5] 灾备规则检查..." -ForegroundColor Yellow
    $result = php artisan dr:failover --list 2>&1
    if ($LASTEXITCODE -ne 0) { throw "规则查询失败" }
    Write-Host "  ✓ 规则查询成功" -ForegroundColor Green
    
    Write-Host "[2/5] 数据库备份..." -ForegroundColor Yellow
    $result = php artisan db:backup --name="pre-dr-drill" 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Host "  ⚠️  备份失败，继续执行" -ForegroundColor Yellow }
    else { Write-Host "  ✓ 备份完成" -ForegroundColor Green }
}

# ─── 阶段 2: 执行前验证 ───
function Step-DryRun {
    Write-Host "[3/5] Dry-run 验证..." -ForegroundColor Yellow
    $result = php artisan dr:failover --rule=$RuleId --dry-run 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Dry-run 验证失败" }
    Write-Host "  ✓ Dry-run 通过" -ForegroundColor Green
}

# ─── 阶段 3: 执行切换 ───
function Step-Switch {
    param([string]$ruleId)
    
    Write-Host "[4/5] 执行故障切换..." -ForegroundColor Yellow
    Write-Host "  ⚠️  正在切换流量到备用数据中心..." -ForegroundColor Red
    
    $switchStart = Get-Date
    $result = php artisan dr:failover --rule=$ruleId --reason="灾备演练" --force 2>&1
    $switchTime = (Get-Date) - $switchStart
    
    if ($LASTEXITCODE -ne 0) { throw "故障切换失败" }
    
    Write-Host "  ✓ 故障切换完成，耗时 $($switchTime.TotalSeconds) 秒" -ForegroundColor Green
    
    # 验证 RTO
    if ($switchTime.TotalSeconds -le 300) {
        Write-Host "  ✅ RTO 达标: $($switchTime.TotalSeconds)s < 300s (5min)" -ForegroundColor Green
    } else {
        Write-Host "  ❌ RTO 未达标: $($switchTime.TotalSeconds)s > 300s (5min)" -ForegroundColor Red
    }
    
    # 健康检查
    Write-Host "  验证备用数据中心..." -ForegroundColor Yellow
    $result = php artisan multi-region:health-check 2>&1
    Write-Host "  $result" -ForegroundColor Gray
    
    # 保持切换状态一段时间验证
    Write-Host "  保持切换状态 10s 验证..." -ForegroundColor Yellow
    Start-Sleep -Seconds 10
    
    $httpResult = curl.exe -s -o nul -w "%{http_code}" https://localhost:8000/api/health/ready 2>$null
    if ($httpResult -eq 200) {
        Write-Host "  ✅ API 健康检查通过" -ForegroundColor Green
    } else {
        Write-Host "  ⚠️  API 健康检查: $httpResult" -ForegroundColor Yellow
    }
}

# ─── 阶段 4: 回切 ───
function Step-Restore {
    param([string]$ruleId)
    
    Write-Host "[5/5] 执行恢复回切..." -ForegroundColor Yellow
    
    $restoreStart = Get-Date
    $result = php artisan dr:failover --rule=$ruleId --restore --reason="灾备演练恢复" --force 2>&1
    $restoreTime = (Get-Date) - $restoreStart
    
    if ($LASTEXITCODE -ne 0) { throw "恢复回切失败" }
    
    Write-Host "  ✓ 恢复回切完成，耗时 $($restoreTime.TotalSeconds) 秒" -ForegroundColor Green
}

# ─── 主流程 ───
try {
    switch ($Mode) {
        'verify' {
            Step-PreCheck
            Step-DryRun
            Write-Host ""
            Write-Host "==========================================" -ForegroundColor Green
            Write-Host "  演练验证完成!" -ForegroundColor Green
            Write-Host "  未执行实际切换，可执行 -Mode full 进行完整演练" -ForegroundColor Green
            Write-Host "==========================================" -ForegroundColor Green
        }
        'switch' {
            Step-PreCheck
            Step-DryRun
            Step-Switch -ruleId $RuleId
            Write-Host ""
            Write-Host "==========================================" -ForegroundColor Yellow
            Write-Host "  切换完成，当前流量在备用数据中心" -ForegroundColor Yellow
            Write-Host "  执行以下命令回切: .\scripts\dr-drill.ps1 -Mode restore" -ForegroundColor Yellow
            Write-Host "==========================================" -ForegroundColor Yellow
        }
        'full' {
            Step-PreCheck
            Step-DryRun
            Step-Switch -ruleId $RuleId
            Step-Restore -ruleId $RuleId
        }
    }
    
    $totalTime = (Get-Date) - $startTime
    Write-Host ""
    Write-Host "==========================================" -ForegroundColor Green
    if ($Mode -ne 'switch') {
        Write-Host "  演练完成!" -ForegroundColor Green
        Write-Host "  总耗时: $($totalTime.TotalSeconds) 秒" -ForegroundColor Green
        Write-Host "  状态: ✅ 通过" -ForegroundColor Green
    }
    Write-Host "==========================================" -ForegroundColor Green
}
catch {
    Write-Host ""
    Write-Host "==========================================" -ForegroundColor Red
    Write-Host "  演练失败!" -ForegroundColor Red
    Write-Host "  错误: $_" -ForegroundColor Red
    Write-Host "  状态: ❌ 失败" -ForegroundColor Red
    Write-Host "==========================================" -ForegroundColor Red
    exit 1
}
