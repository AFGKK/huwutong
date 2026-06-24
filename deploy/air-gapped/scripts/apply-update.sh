#!/bin/bash
# ===================================================
# apply-update.sh
# 离线更新包应用脚本
# 使用方式: bash scripts/apply-update.sh <update_package>
# 说明: 在离线环境应用更新包
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
print_warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

show_usage() {
    echo "使用方式: $0 <update_package_path>"
    echo ""
    echo "参数:"
    echo "  <update_package_path>  离线更新包路径 (*.update.tar.gz)"
    echo ""
    echo "示例:"
    echo "  $0 /mnt/usb/hwt-update-v1.1.0.update.tar.gz"
    echo "  $0 updates/hwt-update-v1.1.0.update.tar.gz"
    exit 1
}

# ---------- 参数 ----------
UPDATE_PKG="${1:-}"
if [ -z "${UPDATE_PKG}" ]; then
    show_usage
fi

if [ ! -f "${UPDATE_PKG}" ]; then
    print_error "更新包不存在: ${UPDATE_PKG}"
    exit 1
fi

# ---------- 检查 Docker ----------
if ! command -v docker &>/dev/null; then
    print_error "Docker 未安装"
    exit 1
fi

# ---------- 获取当前版本 ----------
CURRENT_VERSION=$(docker images --format '{{.Tag}}' hwt-license-api 2>/dev/null | grep -v latest | sort -V | tail -1 || echo "unknown")
print_info "当前版本: ${CURRENT_VERSION}"

# ---------- 创建临时目录 ----------
TMP_DIR=$(mktemp -d)
trap 'rm -rf "${TMP_DIR}"' EXIT

echo ""
echo ">>> [1/4] 解压更新包..."
tar -xzf "${UPDATE_PKG}" -C "${TMP_DIR}"
print_info "解压完成"

echo ""
echo ">>> [2/4] 校验更新包完整性..."
if [ -f "${TMP_DIR}/SHA256SUMS" ]; then
    cd "${TMP_DIR}"
    if sha256sum -c SHA256SUMS --quiet 2>/dev/null; then
        print_info "SHA256 校验通过"
    else
        print_error "SHA256 校验失败"
        exit 1
    fi
    cd "${SCRIPT_DIR}"
fi

# 解析更新信息
UPDATE_VERSION=$(cat "${TMP_DIR}/VERSION" 2>/dev/null || echo "unknown")
print_info "更新版本: ${UPDATE_VERSION}"

echo ""
echo ">>> [3/4] 加载新镜像..."
if [ -d "${TMP_DIR}/docker-images" ]; then
    for tar_file in "${TMP_DIR}/docker-images"/*.tar; do
        [ -f "${tar_file}" ] || continue
        print_info "加载镜像: $(basename "${tar_file}")"
        docker load -i "${tar_file}"
    done
fi

# 执行更新脚本
if [ -f "${TMP_DIR}/scripts/pre-update.sh" ]; then
    print_info "执行前置更新脚本..."
    bash "${TMP_DIR}/scripts/pre-update.sh"
fi

echo ""
echo ">>> [4/4] 重启服务..."
cd "${BASE_DIR}"
if [ -f "docker-compose.yml" ]; then
    docker compose up -d --force-recreate
    sleep 5
    docker compose ps
fi

# 执行后置更新脚本
if [ -f "${TMP_DIR}/scripts/post-update.sh" ]; then
    print_info "执行后置更新脚本..."
    bash "${TMP_DIR}/scripts/post-update.sh"
fi

cd "${SCRIPT_DIR}"

# ---------- 验证 ----------
NEW_VERSION=$(docker images --format '{{.Tag}}' hwt-license-api 2>/dev/null | grep -v latest | sort -V | tail -1 || echo "unknown")

echo ""
echo "=========================================="
echo " ✅ 更新完成!"
echo "    更新前版本: ${CURRENT_VERSION}"
echo "    更新后版本: ${NEW_VERSION}"
echo "=========================================="
