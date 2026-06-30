#!/bin/bash
#
# Pact 契约测试 CI 脚本
# 在 CI 流水线中执行消费者驱动契约测试
#
# 使用方式:
#   bash .ci/contract-test.sh
#
# 流程:
#   1. 生成消费者契约（由 SDK 团队维护）
#   2. 验证提供者契约（确保 API 实现满足契约）
#   3. 报告契约验证结果
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PACT_DIR="${PROJECT_DIR}/pacts"
EXIT_CODE=0

echo "════════════════════════════════════════════"
echo "  Pact 契约测试 - 消费者驱动契约验证"
echo "════════════════════════════════════════════"
echo ""

# Step 1: 检查 Pact 契约文件
echo "📋 步骤 1/4: 检查现有契约..."
echo "----------------------------------------"

if [ -d "$PACT_DIR" ] && [ "$(ls -A "$PACT_DIR" 2>/dev/null)" ]; then
    echo "找到以下契约文件:"
    for pact_file in "$PACT_DIR"/*.json; do
        name=$(basename "$pact_file" .json)
        interactions=$(php -r "echo count(json_decode(file_get_contents('$pact_file'))->interactions ?? []);")
        echo "  ✅ ${name} (${interactions} 个交互)"
    done
else
    echo "  ⚠️  没有找到 Pact 契约文件"
    echo "  首次运行将生成默认契约"
fi
echo ""

# Step 2: 生成消费者契约
echo "🔧 步骤 2/4: 生成消费者契约..."
echo "----------------------------------------"
cd "$PROJECT_DIR"

if php artisan test --filter=PhpSdkContractTest --compact 2>&1; then
    echo "  ✅ 消费者契约生成成功"
else
    echo "  ❌ 消费者契约生成失败"
    EXIT_CODE=1
fi
echo ""

# Step 3: 验证提供者契约
echo "🔍 步骤 3/4: 验证提供者契约..."
echo "----------------------------------------"

if php artisan test --filter=LicenseApiProviderContractTest --compact 2>&1; then
    echo "  ✅ 提供者契约验证通过"
else
    echo "  ❌ 提供者契约验证失败"
    echo "  ℹ️  请检查 API 实现是否与 Pact 契约一致"
    echo "  修复后重新运行: bash .ci/contract-test.sh"
    EXIT_CODE=1
fi
echo ""

# Step 4: 生成报告
echo "📊 步骤 4/4: 契约测试报告"
echo "----------------------------------------"

php artisan contract:list

echo ""
echo "════════════════════════════════════════════"
if [ $EXIT_CODE -eq 0 ]; then
    echo "  ✅ 所有契约测试通过"
else
    echo "  ❌ 契约测试存在失败"
fi
echo "════════════════════════════════════════════"

exit $EXIT_CODE
