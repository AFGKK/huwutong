#!/bin/bash
# ===================================================
# check-integrity.sh
# 离线环境完整性校验脚本
# 使用方式: bash scripts/check-integrity.sh [--verbose]
# 说明: 校验离线包文件完整性、Docker 镜像完整性、
#       文件权限和环境配置
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

INFO="${GREEN}[INFO]${NC}"
WARN="${YELLOW}[WARN]${NC}"
ERR="${RED}[ERROR]${NC}"

PASS=0
FAIL=0
WARN_COUNT=0

check() {
    local desc="$1"
    local cmd="$2"
    local severity="${3:-error}"

    if eval "${cmd}" &>/dev/null; then
        echo -e "  ${INFO} [PASS] ${desc}"
        PASS=$((PASS + 1))
    else
        if [ "${severity}" = "warn" ]; then
            echo -e "  ${WARN} [WARN] ${desc}"
            WARN_COUNT=$((WARN_COUNT + 1))
        else
            echo -e "  ${ERR} [FAIL] ${desc}"
            FAIL=$((FAIL + 1))
        fi
    fi
}

echo "=========================================="
echo " HWT License 离线包完整性校验"
echo "=========================================="
echo ""

# ---------- 文件结构检查 ----------
echo ">>> 文件结构检查..."

check "根目录存在" "[ -d '${BASE_DIR}' ]"
check "SHA256SUMS 存在" "[ -f '${BASE_DIR}/SHA256SUMS' ]" "warn"
check "MANIFEST 存在" "[ -f '${BASE_DIR}/MANIFEST' ]" "warn"
check ".env 存在" "[ -f '${BASE_DIR}/.env' ]"
check "docker-compose.yml 存在" "[ -f '${BASE_DIR}/docker-compose.yml' ]" "warn"
check "docker-images 目录存在" "[ -d '${BASE_DIR}/docker-images' ]"
check "scripts 目录存在" "[ -d '${BASE_DIR}/scripts' ]"
check "config 目录存在" "[ -d '${BASE_DIR}/config' ]" "warn"

# ---------- Docker 镜像检查 ----------
echo ""
echo ">>> Docker 镜像完整性检查..."

if [ -d "${BASE_DIR}/docker-images" ]; then
    TAR_COUNT=$(ls "${BASE_DIR}/docker-images"/*.tar 2>/dev/null | wc -l)
    check "Docker 镜像文件数量 > 0" "[ '${TAR_COUNT}' -gt 0 ]"

    # 检查每个 tar 文件
    for tar_file in "${BASE_DIR}/docker-images"/*.tar; do
        [ -f "${tar_file}" ] || continue
        tar_name=$(basename "${tar_file}")
        tar_size=$(stat -c%s "${tar_file}" 2>/dev/null || stat -f%z "${tar_file}" 2>/dev/null || echo 0)
        check "镜像 ${tar_name} 大小 (${tar_size} bytes > 0)" "[ '${tar_size}' -gt 1000000 ]" "warn"
    done
fi

# ---------- SHA256 校验 ----------
echo ""
echo ">>> SHA256 校验..."

if [ -f "${BASE_DIR}/SHA256SUMS" ]; then
    cd "${BASE_DIR}"
    if sha256sum -c SHA256SUMS --quiet 2>/dev/null; then
        check "ALL SHA256 校验通过" "true"
    else
        # 找出具体哪个文件失败
        FAILED=$(sha256sum -c SHA256SUMS 2>/dev/null | grep -i "FAILED" | wc -l)
        if [ "${FAILED}" -gt 0 ]; then
            echo -e "  ${ERR} 有 ${FAILED} 个文件校验失败:"
            sha256sum -c SHA256SUMS 2>/dev/null | grep -i "FAILED" | sed 's/^/    /'
            FAIL=$((FAIL + FAILED))
        fi
    fi
    cd "${SCRIPT_DIR}"
fi

# ---------- 脚本检查 ----------
echo ""
echo ">>> 部署脚本检查..."

for script in deploy-offline.sh import-license.sh check-integrity.sh; do
    script_path="${BASE_DIR}/scripts/${script}"
    check "脚本 ${script} 存在" "[ -f '${script_path}' ]"
    check "脚本 ${script} 可执行" "[ -x '${script_path}' ]" "warn"

    # 检查脚本是否有语法错误 (Bash)
    if [ -f "${script_path}" ]; then
        check "脚本 ${script} Bash 语法正确" "bash -n '${script_path}'"
    fi
done

# ---------- 环境检查 ----------
echo ""
echo ">>> 目标环境检查..."

check "Docker 已安装" "command -v docker"
check "Docker Compose 已安装" "command -v docker && docker compose version 2>/dev/null" "warn"
check "Python3 已安装" "command -v python3" "warn"
check "curl 已安装" "command -v curl" "warn"
check "unzip 已安装" "command -v unzip" "warn"

# ---------- 网络隔离检查 ----------
echo ""
echo ">>> 网络隔离检查..."

# 确认目标环境确实无外网
if command -v curl &>/dev/null; then
    if curl -s --connect-timeout 3 https://www.google.com &>/dev/null; then
        echo -e "  ${WARN} 目标环境似乎可以访问互联网，这不是严格的气隙环境"
    else
        check "网络隔离验证 (无法访问外网)" "curl -s --connect-timeout 3 https://www.google.com 2>&1 || true" "warn"
    fi
fi

# ---------- 总结 ----------
echo ""
echo "=========================================="
echo " 校验结果"
echo "=========================================="
echo -e "  ${INFO} 通过: ${PASS}"
echo -e "  ${WARN} 警告: ${WARN_COUNT}"
echo -e "  ${ERR} 失败: ${FAIL}"
echo "=========================================="

if [ "${FAIL}" -gt 0 ]; then
    echo -e " ${ERR} 发现 ${FAIL} 个严重问题，建议修复后重新部署"
    exit 1
elif [ "${WARN_COUNT}" -gt 0 ]; then
    echo -e " ${WARN} 存在 ${WARN_COUNT} 个警告，但不影响基本功能"
    exit 0
else
    echo -e " ${INFO} 所有检查通过! ✅"
    exit 0
fi
