#!/bin/bash
# ─── Protobuf PHP 代码生成脚本 ───
# M1.3-28 gRPC 服务间通信
# 使用方法: bash deploy/grpc/scripts/generate-protos.sh
# 前置条件: protoc + protoc-gen-php-grpc 已安装

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../../.." && pwd)"
PROTO_DIR="$PROJECT_DIR/protos"
OUTPUT_DIR="$PROJECT_DIR/protos/generated"

echo "=== gRPC Protobuf 代码生成 ==="
echo "Proto 目录: $PROTO_DIR"
echo "输出目录: $OUTPUT_DIR"

# 创建输出目录
mkdir -p "$OUTPUT_DIR"

# 检查 protoc
if ! command -v protoc &> /dev/null; then
    echo "⚠️  protoc 未安装，请先安装:"
    echo "   Windows: choco install protoc"
    echo "   macOS:   brew install protobuf"
    echo "   Linux:   apt install protobuf-compiler"
    exit 1
fi

PROTOC_VERSION=$(protoc --version)
echo "protoc: $PROTOC_VERSION"

# 检查 grpc_php_plugin
GRPC_PLUGIN=$(which grpc_php_plugin 2>/dev/null || echo "")
if [ -z "$GRPC_PLUGIN" ]; then
    echo "⚠️  grpc_php_plugin 未找到，仅生成 PHP 消息类（不含 gRPC 服务代码）"
    echo "   如需 gRPC 服务代码，请安装 protoc-gen-php-grpc"
    echo ""

    # 仅生成消息类
    protoc \
        --proto_path="$PROTO_DIR" \
        --php_out="$OUTPUT_DIR" \
        "$PROTO_DIR"/*.proto

    echo "✅ PHP 消息类已生成到: $OUTPUT_DIR"
else
    echo "grpc_php_plugin: $GRPC_PLUGIN"

    # 生成消息类 + gRPC 服务代码
    protoc \
        --proto_path="$PROTO_DIR" \
        --php_out="$OUTPUT_DIR" \
        --grpc_out="$OUTPUT_DIR" \
        --plugin=protoc-gen-grpc="$GRPC_PLUGIN" \
        "$PROTO_DIR"/*.proto

    echo "✅ PHP 消息类 + gRPC 服务代码已生成到: $OUTPUT_DIR"
fi

# 生成文件列表
echo ""
echo "生成的文件:"
find "$OUTPUT_DIR" -name "*.php" | sort

echo ""
echo "=== 完成 ==="
echo "请将以下内容添加到 composer.json autoload:"
echo '  "psr-4": {'
echo '    "App\\\\Services\\\\Grpc\\\\Proto\\\\": "protos/generated/"'
echo '  }'
