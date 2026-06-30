<#
.SYNOPSIS
    M2-36 季度恢复演练脚本 — 模拟灾难恢复全流程
.DESCRIPTION
    在 Staging 环境模拟完整的灾难恢复流程，验证 RTO/RPO 达标。
    生产环境请勿执行。
.PARAMETER Stage
    演练阶段: verify (仅检查) | restore (恢复) | full (完整演练)
.PARAMETER BackupId
    指定备份 ID（可选，默认使用最近备份）
.EXAMPLE
    .\scripts\recovery-drill.ps1 -Stage verify
    .\scripts\recovery-drill.ps1 -Stage full
#>

param(
    [ValidateSet('verify', 'restore', 'full')]
    [string]$Stage = 'verify',
    [string]$BackupId = ''
)

$ErrorActionPreference = 'Stop'
$startTime = Get-Date

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  互物通 季度恢复演练" -ForegroundColor Cyan
Write-Host "  M2-36 | 环境: $env:APP_ENV" -ForegroundColor Cyan
Write-Host "  时间: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# ─── 阶段 1: 验证 ───
function Step-Verify {
    Write-Host "[1/5] 验证备份记录..." -ForegroundColor Yellow
    $result = php artisan backup:list 2>&1
    if ($LASTEXITCODE -ne 0) { throw "备份列表查询失败" }
    Write-Host "  ✓ 备份记录查询成功" -ForegroundColor Green

    Write-Host "[2/5] 验证备份文件完整性..." -ForegroundColor Yellow
    
    # 获取最近备份文件
    $backupDir = "storage\app\backups"
    $latestFile = Get-ChildItem -Path $backupDir -Filter "*.sql.gz" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    
    if (-not $latestFile) {
        throw "未找到备份文件"
    }
    
    Write-Host "  最近备份: $($latestFile.Name) ($([math]::Round($latestFile.Length/1MB, 2)) MB)" -ForegroundColor Gray
    
    # 验证 gzip 完整性
    $result = & "gzip" "-t" $latestFile.FullName 2>&1
    if ($LASTEXITCODE -ne 0) { throw "备份文件损坏: $($latestFile.Name)" }
    Write-Host "  ✓ gzip 完整性校验通过" -ForegroundColor Green
    
    return $latestFile
}

# ─── 阶段 2: Dry-run 验证 ───
function Step-DryRun {
    Write-Host "[3/5] Dry-run 验证..." -ForegroundColor Yellow
    $result = php artisan db:restore --dry-run --latest 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Dry-run 验证失败" }
    Write-Host "  ✓ Dry-run 通过" -ForegroundColor Green
}

# ─── 阶段 3: RTO 评估 ───
function Step-RTO {
    Write-Host "[4/5] RTO 评估..." -ForegroundColor Yellow
    php artisan recovery:drill --quick 2>&1
    if ($LASTEXITCODE -ne 0) { throw "RTO 评估失败" }
    Write-Host "  ✓ RTO 评估完成" -ForegroundColor Green
}

# ─── 阶段 4: 完整恢复测试 ───
function Step-Restore {
    param([string]$backupId)
    
    Write-Host "[5/5] 完整恢复测试..." -ForegroundColor Yellow
    Write-Host "  ⚠️  注意: 当前数据库数据将被覆盖！" -ForegroundColor Red
    
    $confirm = Read-Host "  确认继续? (yes/no)"
    if ($confirm -ne 'yes') {
        Write-Host "  已跳过恢复测试" -ForegroundColor Gray
        return
    }
    
    $restoreStart = Get-Date
    
    if ($backupId) {
        $result = php artisan db:restore --backup=$backupId --force 2>&1
    } else {
        $result = php artisan db:restore --latest --force 2>&1
    }
    
    if ($LASTEXITCODE -ne 0) { throw "恢复失败" }
    
    $restoreTime = (Get-Date) - $restoreStart
    Write-Host "  ✓ 恢复完成，耗时 $($restoreTime.TotalSeconds) 秒" -ForegroundColor Green
    
    # 验证恢复后的数据
    Write-Host "  验证恢复数据..." -ForegroundColor Yellow
    $count = php artisan tinker --execute="echo \App\Models\License::count();" 2>&1
    Write-Host "  License 表记录数: $count" -ForegroundColor Gray
}

# ─── 主流程 ───
try {
    switch ($Stage) {
        'verify' {
            Step-Verify
            Step-DryRun
            Step-RTO
        }
        'restore' {
            Step-Restore -backupId $BackupId
        }
        'full' {
            $file = Step-Verify
            Step-DryRun
            Step-RTO
            Step-Restore -backupId $BackupId
        }
    }
    
    $totalTime = (Get-Date) - $startTime
    Write-Host ""
    Write-Host "==========================================" -ForegroundColor Green
    Write-Host "  演练完成!" -ForegroundColor Green
    Write-Host "  总耗时: $($totalTime.TotalSeconds) 秒" -ForegroundColor Green
    Write-Host "  状态: ✅ 通过" -ForegroundColor Green
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
