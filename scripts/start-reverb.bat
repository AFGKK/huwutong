@echo off
REM 启动 Laravel Reverb WebSocket 服务器
REM 使用: double-click 或在终端运行

echo ========================================
echo  互物通 - Laravel Reverb WebSocket 服务器
echo ========================================
echo.

cd /d "%~dp0.."

echo [1/2] 检查 PHP 可用性...
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未找到 PHP，请确保 PHP 已添加到 PATH
    pause
    exit /b 1
)
echo [OK] PHP 可用

echo [2/2] 启动 Reverb 服务 (端口 8080)...
echo 提示: 保持此窗口打开以维持 WebSocket 连接
echo 按 Ctrl+C 停止服务
echo.

php artisan reverb:start --host=0.0.0.0 --port=8080

echo.
echo 服务已停止
pause
