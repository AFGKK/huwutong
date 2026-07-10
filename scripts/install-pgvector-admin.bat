@echo off
REM 以管理员身份运行此脚本安装 pgvector
REM 右键 -> 以管理员身份运行

set PGROOT=C:\Program Files\PostgreSQL\16
set SRC=%~dp0..\storage\pgvector-win

echo === 安装 pgvector 到 PostgreSQL 16 ===

if not exist "%SRC%\lib\vector.dll" (
    for /r "%SRC%" %%f in (vector.dll) do set DLL=%%f
) else (
    set DLL=%SRC%\lib\vector.dll
)

for /r "%SRC%" %%f in (vector.control) do set CTL=%%f
for /r "%SRC%" %%f in (vector--*.sql) do copy /Y "%%f" "%PGROOT%\share\extension\" >nul

if not defined DLL (
    echo 错误: 未找到 vector.dll，请先运行 install-pgvector-windows.ps1 下载
    pause
    exit /b 1
)

copy /Y "%DLL%" "%PGROOT%\lib\vector.dll"
copy /Y "%CTL%" "%PGROOT%\share\extension\vector.control"

echo 创建扩展...
"%PGROOT%\bin\psql.exe" -U postgres -d huwutong -c "CREATE EXTENSION IF NOT EXISTS vector;"
"%PGROOT%\bin\psql.exe" -U postgres -d huwutong -c "SELECT extname, extversion FROM pg_extension WHERE extname='vector';"

echo.
echo 完成！
pause
