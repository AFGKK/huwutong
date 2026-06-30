@echo off
REM ─── Protobuf PHP 代码生成脚本 (Windows) ───
REM M1.3-28 gRPC 服务间通信
REM 使用方法: deploy\grpc\scripts\generate-protos.bat
REM 前置条件: protoc 已安装

echo === gRPC Protobuf 代码生成 (Windows) ===

set PROTO_DIR=protos
set OUTPUT_DIR=protos\generated

if not exist "%OUTPUT_DIR%" mkdir "%OUTPUT_DIR%"

REM 检查 protoc
where protoc >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo. 
    echo [WARNING] protoc 未安装，请先安装:
    echo   choco install protoc
    echo. 
    pause
    exit /b 1
)

protoc --version

REM 生成 PHP 消息类（不含 gRPC 服务代码）
protoc --proto_path="%PROTO_DIR%" --php_out="%OUTPUT_DIR%" "%PROTO_DIR%\*.proto"

echo.
echo [DONE] PHP 消息类已生成到: %OUTPUT_DIR%
echo.
echo 请将以下内容添加到 composer.json autoload:
echo   "psr-4": {
echo     "App\\Services\\Grpc\\Proto\\": "protos/generated/"
echo   }

pause
