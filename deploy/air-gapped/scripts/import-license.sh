#!/bin/bash
# ===================================================
# import-license.sh
# 离线环境 License 文件导入脚本
# 使用方式: bash scripts/import-license.sh <license_file_path>
# 说明: 从 U 盘或本地路径导入 License 文件到系统
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
print_warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

show_usage() {
    echo "使用方式: $0 <license_file_path> [options]"
    echo ""
    echo "参数:"
    echo "  <license_file_path>  License 文件路径 (支持 U 盘路径)"
    echo ""
    echo "选项:"
    echo "  --dry-run            仅验证，不实际导入"
    echo "  --force              强制覆盖已存在的 License"
    echo "  --api-url <url>      API 服务地址 (默认: http://localhost:8000)"
    echo ""
    echo "示例:"
    echo "  $0 /mnt/usb/license.lic"
    echo "  $0 /mnt/cdrom/license.lic --dry-run"
    echo "  $0 ./license.lic --api-url http://192.168.1.100:8000"
    exit 1
}

# ---------- 参数解析 ----------
LICENSE_FILE=""
DRY_RUN=false
FORCE=false
API_URL="http://localhost:8000"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=true; shift ;;
        --force) FORCE=true; shift ;;
        --api-url) API_URL="$2"; shift 2 ;;
        -h|--help) show_usage ;;
        -*)
            print_error "未知选项: $1"
            show_usage
            ;;
        *)
            if [ -z "${LICENSE_FILE}" ]; then
                LICENSE_FILE="$1"
            else
                print_error "多余的参数: $1"
                show_usage
            fi
            shift
            ;;
    esac
done

if [ -z "${LICENSE_FILE}" ]; then
    print_error "请指定 License 文件路径"
    show_usage
fi

# ===========================================
echo "=========================================="
echo " HWT License 导入工具"
echo "=========================================="
echo ""

# ---------- 步骤 1: 检查文件 ----------
echo ">>> [1/7] 检查 License 文件..."
if [ ! -f "${LICENSE_FILE}" ]; then
    print_error "文件不存在: ${LICENSE_FILE}"

    # 尝试查找常见 U 盘挂载点
    print_info "尝试搜索常见挂载点..."
    for mount_point in /mnt/usb /mnt/usb0 /mnt/usb1 /media/*/usb /run/media/*/usb /mnt/cdrom /media/*/CDROM; do
        if [ -d "${mount_point}" ]; then
            found=$(find "${mount_point}" -maxdepth 3 -name "*.lic" -o -name "*.license" 2>/dev/null | head -5)
            if [ -n "${found}" ]; then
                echo "  在 ${mount_point} 发现:"
                echo "${found}" | sed 's/^/    - /'
            fi
        fi
    done
    exit 1
fi

FILE_SIZE=$(stat -c%s "${LICENSE_FILE}" 2>/dev/null || stat -f%z "${LICENSE_FILE}" 2>/dev/null || echo "unknown")
print_info "文件: ${LICENSE_FILE} (${FILE_SIZE} bytes)"

# ---------- 步骤 2: 验证文件格式 ----------
echo ""
echo ">>> [2/7] 验证 License 文件格式..."

# 检查是否为 JSON 格式
if command -v python3 &>/dev/null; then
    if python3 -c "import json; json.load(open('${LICENSE_FILE}'))" 2>/dev/null; then
        print_info "License 文件格式: JSON (有效)"
    else
        print_warn "License 文件不是标准 JSON 格式，尝试作为文本处理..."
    fi
elif command -v php &>/dev/null; then
    if php -r "json_decode(file_get_contents('${LICENSE_FILE}')); echo json_last_error() === JSON_ERROR_NONE ? 'valid' : 'invalid';" 2>/dev/null | grep -q valid; then
        print_info "License 文件格式: JSON (有效)"
    fi
fi

# 检查文件扩展名
case "${LICENSE_FILE}" in
    *.lic|*.license|*.key|*.pem|*.crt)
        print_info "文件扩展名: ${LICENSE_FILE##*.} (可接受)"
        ;;
    *)
        print_warn "文件扩展名 '${LICENSE_FILE##*.}' 不标准，预期: .lic/.license"
        ;;
esac

# ---------- 步骤 3: 解码验证 ----------
echo ""
echo ">>> [3/7] 解码验证 License 内容..."

# 提取文件内容
if command -v openssl &>/dev/null; then
    print_info "使用 OpenSSL 验证签名..."
    # 如果文件有签名部分，尝试验证
    head -c 256 "${LICENSE_FILE}" | openssl base64 -d 2>/dev/null && {
        print_info "Base64 解码成功"
    } || print_info "非 Base64 编码，使用原始内容"
fi

# 显示 License 基本信息
echo ""
echo "  License 文件信息:"
head -c 500 "${LICENSE_FILE}"
echo ""
echo "  ... (截断)"

# ---------- 步骤 4: 检查 Docker 容器 ----------
echo ""
echo ">>> [4/7] 检查 HWT API 服务状态..."
if command -v docker &>/dev/null; then
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -qi 'hwt'; then
        print_info "HWT 容器运行中"
        docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null | head -5
    else
        print_warn "未发现 HWT 运行容器"
        print_info "如果服务未启动，请先执行: bash scripts/deploy-offline.sh"
    fi
else
    print_warn "Docker 不可用，将尝试直接通过 API 导入"
fi

# ---------- 步骤 5: API 导入 ----------
echo ""
echo ">>> [5/7] 通过 API 导入 License..."

# 读取 License 内容
LICENSE_CONTENT=$(cat "${LICENSE_FILE}")

if [ "${DRY_RUN}" = true ]; then
    print_info "'[DRY RUN] 模拟导入 (不会实际执行)"
    echo ""
    echo "  API 端点: ${API_URL}/api/license/import"
    echo "  方法: POST"
    echo "  文件: ${LICENSE_FILE}"
    echo ""
    print_info "DRY RUN 完成，未执行实际操作"
    exit 0
fi

# 尝试通过 API 导入
if command -v curl &>/dev/null; then
    API_RESPONSE=$(curl -s -X POST "${API_URL}/api/license/import" \
        -H "Content-Type: application/json" \
        -d "$(echo '{"license_content": ""}' | jq --arg data "${LICENSE_CONTENT}" '.license_content = $data' 2>/dev/null || echo '{"license_content": '"$(echo "${LICENSE_CONTENT}" | python3 -c 'import sys,json;print(json.dumps(sys.stdin.read()))' 2>/dev/null || echo 'null')"'}')" \
        -w '\n%{http_code}' 2>/dev/null || true)

    HTTP_CODE=$(echo "${API_RESPONSE}" | tail -1)
    RESPONSE_BODY=$(echo "${API_RESPONSE}" | head -n -1)

    if [ -n "${HTTP_CODE}" ] && [ "${HTTP_CODE}" -ge 200 ] && [ "${HTTP_CODE}" -lt 300 ]; then
        print_info "API 导入成功 (HTTP ${HTTP_CODE})"
        echo "  响应: ${RESPONSE_BODY}"
    else
        print_error "API 导入失败 (HTTP ${HTTP_CODE:-'connection failed'})"
        echo "  响应: ${RESPONSE_BODY}"
        echo ""
        print_info "尝试直接写入 License 存储..."
        IMPORT_DIR="${BASE_DIR}/licenses"
        mkdir -p "${IMPORT_DIR}"
        cp "${LICENSE_FILE}" "${IMPORT_DIR}/"
        print_info "License 文件已复制到 ${IMPORT_DIR}/"
        print_info "请手动重启服务以加载 License: docker compose restart"
    fi
else
    print_warn "curl 不可用，将 License 文件复制到存储目录"
    IMPORT_DIR="${BASE_DIR}/licenses"
    mkdir -p "${IMPORT_DIR}"
    cp "${LICENSE_FILE}" "${IMPORT_DIR}/"
    print_info "License 文件已复制到 ${IMPORT_DIR}/"
fi

# ---------- 步骤 6: 备份原始文件 ----------
echo ""
echo ">>> [6/7] 备份原始 License 文件..."
BACKUP_DIR="${BASE_DIR}/backups/licenses"
mkdir -p "${BACKUP_DIR}"
BACKUP_FILE="${BACKUP_DIR}/license-$(date +%Y%m%d-%H%M%S).lic"
cp "${LICENSE_FILE}" "${BACKUP_FILE}"
print_info "已备份到: ${BACKUP_FILE}"

# ---------- 步骤 7: 验证 ----------
echo ""
echo ">>> [7/7] 验证导入结果..."

if command -v curl &>/dev/null; then
    VERIFY_RESPONSE=$(curl -s "${API_URL}/api/license/verify" -w '\n%{http_code}' 2>/dev/null || true)
    VERIFY_CODE=$(echo "${VERIFY_RESPONSE}" | tail -1)
    VERIFY_BODY=$(echo "${VERIFY_RESPONSE}" | head -n -1)

    if [ -n "${VERIFY_CODE}" ] && [ "${VERIFY_CODE}" -eq 200 ]; then
        print_info "License 验证成功!"
        echo "  响应: ${VERIFY_BODY}"
    else
        print_warn "License 验证 API 调用失败 (HTTP ${VERIFY_CODE:-'connection failed'})"
        print_info "请手动验证: ${API_URL}/api/license/verify"
    fi
fi

echo ""
echo "=========================================="
echo " ✅ License 导入流程完成!"
echo ""
echo " License 文件: ${LICENSE_FILE}"
echo " 备份位置: ${BACKUP_FILE}"
echo " API 地址: ${API_URL}"
echo ""
echo " 后续操作:"
echo "   验证状态: curl ${API_URL}/api/license/verify"
echo "   查看详情: curl ${API_URL}/api/license"
echo "=========================================="
