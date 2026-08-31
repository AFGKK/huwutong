@echo off
REM D-20: 互物通 Docker 全栈一键启动
REM 用法: docker-start.cmd [up|down|restart|status|fresh|logs]
REM 默认: up

if "%1"=="" set "ACTION=up" else set "ACTION=%1"

set COMPOSE_FILE=docker-compose.yml

if "%ACTION%"=="up" (
    echo [D-20] 正在启动互物通全栈开发环境...
    docker compose -f %COMPOSE_FILE% up -d
    if %ERRORLEVEL% equ 0 (
        echo.
        echo [OK] 全栈已启动！
        echo.
        echo   网站:     http://localhost
        echo   邮件:     http://localhost:8025
        echo   Meilisearch: http://localhost:7700
        echo   Ollama:   http://localhost:11434
        echo.
        echo   运行 docker-start.cmd logs 查看日志
    ) else (
        echo [FAIL] 启动失败
    )
    goto :eof
)

if "%ACTION%"=="down" (
    echo [D-20] 正在关闭互物通全栈开发环境...
    docker compose -f %COMPOSE_FILE% down
    if %ERRORLEVEL% equ 0 (
        echo [OK] 全栈已关闭
    ) else (
        echo [FAIL] 关闭失败
    )
    goto :eof
)

if "%ACTION%"=="restart" (
    echo [D-20] 正在重启互物通全栈...
    docker compose -f %COMPOSE_FILE% restart
    goto :eof
)

if "%ACTION%"=="status" (
    docker compose -f %COMPOSE_FILE% ps
    goto :eof
)

if "%ACTION%"=="fresh" (
    echo [D-20] 正在重建数据库并填充种子数据...
    docker compose -f %COMPOSE_FILE% run --rm app php artisan migrate:fresh --seed --force
    goto :eof
)

if "%ACTION%"=="logs" (
    docker compose -f %COMPOSE_FILE% logs -f
    goto :eof
)

echo 用法: docker-start.cmd [up^|down^|restart^|status^|fresh^|logs]
echo.
echo   up        启动全栈环境（默认）
echo   down      关闭全栈环境
echo   restart   重启全栈
echo   status    查看容器状态
echo   fresh     重建数据库 + 种子数据
echo   logs      跟踪日志
