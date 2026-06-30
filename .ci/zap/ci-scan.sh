#!/bin/bash
# ============================================================
# OWASP ZAP 自动化渗透测试 - CI 集成脚本
# M2-112: 每次发布前自动执行 + 高危漏洞阻断发布
# ============================================================
#
# 使用方式:
#   export TARGET_URL=http://localhost:8000
#   bash .ci/zap/quick-scan.sh              # 快速基线扫描
#   bash .ci/zap/full-scan.sh               # 完整渗透测试
#   bash .ci/zap/api-scan.sh                # API 专项扫描
#   bash .ci/zap/ci-scan.sh                 # CI 流水线全流程
#
# 依赖:
#   - Docker
#   - OWASP ZAP 镜像: ghcr.io/zaproxy/zaproxy:stable
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

# ─── 配置 ───
TARGET_URL="${TARGET_URL:-http://localhost:8000}"
API_TOKEN="${ZAP_API_TOKEN:-}"
REPORT_DIR="${SCRIPT_DIR}/reports"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FAIL_ON_HIGH="${FAIL_ON_HIGH:-true}"       # 高危漏洞是否阻断发布
FAIL_ON_MEDIUM="${FAIL_ON_MEDIUM:-false}"  # 中危漏洞是否阻断发布

mkdir -p "${REPORT_DIR}"

# ─── 颜色输出 ───
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# ─── 前置检查 ───
preflight_check() {
    info "检查 Docker 环境..."
    if ! command -v docker &> /dev/null; then
        error "Docker 未安装，请先安装 Docker Desktop 或 Docker Engine"
        exit 1
    fi

    if ! docker info &> /dev/null; then
        error "Docker 守护进程未运行"
        exit 1
    fi

    # 检查 ZAP 镜像
    if ! docker image inspect ghcr.io/zaproxy/zaproxy:stable &> /dev/null; then
        info "拉取 OWASP ZAP 镜像..."
        docker pull ghcr.io/zaproxy/zaproxy:stable
    fi

    # 检查目标是否可达
    if ! curl -s -o /dev/null --connect-timeout 5 "${TARGET_URL}"; then
        warn "目标 ${TARGET_URL} 不可达，请确保应用已启动"
        warn "请运行: php artisan serve --port=8000"
        exit 1
    fi

    info "前置检查通过"
}

# ─── 解析 XML 报告 ───
parse_xml_report() {
    local report_file="$1"
    if [ ! -f "$report_file" ]; then
        echo "0 0 0"
        return
    fi

    local high med low
    high=$(grep -oP 'risk="High"' "$report_file" | wc -l)
    med=$(grep -oP 'risk="Medium"' "$report_file" | wc -l)
    low=$(grep -oP 'risk="Low"' "$report_file" | wc -l)
    echo "$high $med $low"
}

# ─── 扫描结果判断 ───
evaluate_results() {
    local report_file="$1"
    local scan_name="$2"
    local exit_code=0

    read -r high med low <<< "$(parse_xml_report "$report_file")"

    echo ""
    echo "════════════════════════════════════════"
    echo "  ${scan_name} - 安全扫描结果"
    echo "════════════════════════════════════════"
    echo "  🔴 高危: ${high}"
    echo "  🟡 中危: ${med}"
    echo "  🔵 低危: ${low}"
    echo "════════════════════════════════════════"
    echo ""

    # 生成 Markdown 报告
    local md_report="${REPORT_DIR}/${scan_name}-${TIMESTAMP}.md"
    {
        echo "# ${scan_name} - 安全扫描报告"
        echo ""
        echo "| 严重级别 | 数量 |"
        echo "|---------|:---:|"
        echo "| 🔴 高危 | ${high} |"
        echo "| 🟡 中危 | ${med} |"
        echo "| 🔵 低危 | ${low} |"
        echo ""
        echo "扫描时间: $(date '+%Y-%m-%d %H:%M:%S')"
        echo "扫描目标: ${TARGET_URL}"
    } > "$md_report"
    info "报告已保存: ${md_report}"

    # 根据策略决定是否阻断
    if [ "$FAIL_ON_HIGH" = "true" ] && [ "$high" -gt 0 ]; then
        error "发现 ${high} 个高危漏洞，阻断发布！"
        error "请修复高危漏洞后重新运行扫描"
        exit_code=1
    fi

    if [ "$FAIL_ON_MEDIUM" = "true" ] && [ "$med" -gt 0 ]; then
        error "发现 ${med} 个中危漏洞，阻断发布！"
        exit_code=1
    fi

    if [ "$high" -eq 0 ] && [ "$med" -eq 0 ]; then
        info "🎉 扫描通过，未发现高危/中危漏洞"
    fi

    return $exit_code
}

# ─── 清理 ───
cleanup() {
    info "清理 ZAP 容器..."
    docker compose -f "$COMPOSE_FILE" down --remove-orphans 2>/dev/null || true
}

trap cleanup EXIT

# ─── 基线扫描（无主动攻击，快速） ───
run_baseline_scan() {
    info "开始基线安全扫描: ${TARGET_URL}"

    docker compose -f "$COMPOSE_FILE" run --rm \
        -e TARGET_URL="${TARGET_URL}" \
        -e ZAP_JAVA_OPTS="-Xmx1024m" \
        --name "hwt-zap-baseline-${TIMESTAMP}" \
        zap-baseline || true

    evaluate_results "${REPORT_DIR}/baseline-report.xml" "基线扫描"
}

# ─── 主动扫描（完整渗透测试） ───
run_full_scan() {
    info "开始完整渗透测试: ${TARGET_URL}"

    docker compose -f "$COMPOSE_FILE" run --rm \
        -e TARGET_URL="${TARGET_URL}" \
        -e ZAP_JAVA_OPTS="-Xmx2048m" \
        --name "hwt-zap-full-${TIMESTAMP}" \
        zap-full || true

    evaluate_results "${REPORT_DIR}/full-report.xml" "完整渗透测试"
}

# ─── API 扫描 ───
run_api_scan() {
    local openapi_spec="${1:-${PROJECT_DIR}/storage/api-docs/openapi.yaml}"

    if [ ! -f "$openapi_spec" ]; then
        warn "OpenAPI 规范文件未找到: ${openapi_spec}"
        warn "请先生成 API 文档: php artisan api-docs:generate"
        return 0
    fi

    info "开始 API 安全扫描: ${openapi_spec}"

    # 复制 OpenAPI 规范到 ZAP 可访问目录
    cp "$openapi_spec" "${SCRIPT_DIR}/openapi.yaml"

    docker compose -f "$COMPOSE_FILE" run --rm \
        -e OPENAPI_SPEC="/zap/openapi/openapi.yaml" \
        -e ZAP_JAVA_OPTS="-Xmx1024m" \
        --name "hwt-zap-api-${TIMESTAMP}" \
        zap-api || true

    evaluate_results "${REPORT_DIR}/api-report.xml" "API 安全扫描"
}

# ─── 主函数 ───
main() {
    echo ""
    echo "╔═══════════════════════════════════════════╗"
    echo "║   OWASP ZAP 自动化渗透测试                  ║"
    echo "║   互物通 - 企业授权管理系统                  ║"
    echo "║   M2-112: 安全扫描 CI 集成                  ║"
    echo "╚═══════════════════════════════════════════╝"
    echo ""
    echo "目标: ${TARGET_URL}"
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""

    preflight_check

    local exit_code=0

    case "${1:-ci}" in
        baseline)
            run_baseline_scan
            exit_code=$?
            ;;
        full)
            run_full_scan
            exit_code=$?
            ;;
        api)
            run_api_scan "${2:-}"
            exit_code=$?
            ;;
        ci)
            # CI 流水线全流程：基线 → API 扫描
            info "========== CI 安全扫描流水线 =========="

            run_baseline_scan
            local baseline_exit=$?

            run_api_scan
            local api_exit=$?

            if [ $baseline_exit -ne 0 ] || [ $api_exit -ne 0 ]; then
                exit_code=1
            fi
            ;;
        *)
            echo "使用方法: $0 {baseline|full|api|ci}"
            echo ""
            echo "  baseline  - 快速基线扫描（无主动攻击，推荐 CI 使用）"
            echo "  full      - 完整渗透测试（含主动攻击，耗时较长）"
            echo "  api       - API 专项扫描（基于 OpenAPI 规范）"
            echo "  ci        - CI 流水线全流程（默认）"
            exit 1
            ;;
    esac

    if [ $exit_code -eq 0 ]; then
        info "✅ 安全扫描通过"
    else
        error "❌ 安全扫描存在风险，请修复后重新运行"
    fi

    exit $exit_code
}

main "$@"
