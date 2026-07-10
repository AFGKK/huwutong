# Meilisearch 停止脚本
$proc = Get-Process -Name "meilisearch" -ErrorAction SilentlyContinue
if ($proc) {
    $proc | Stop-Process -Force
    Write-Host "Meilisearch 已停止"
} else {
    Write-Host "Meilisearch 未在运行"
}
