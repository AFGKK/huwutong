#!/bin/bash
# ============================================================
# 自动化渗透测试脚本 — OWASP ZAP API 扫描
# M2-112 自动化渗透测试 CI集成
# ============================================================
#
# 使用:
#   ./zap-scan.sh [target_url] [scan_policy] [output_file]
#
# 环境变量:
#   ZAP_API_KEY       - ZAP API Key (必填)
#   ZAP_ENDPOINT      - ZAP API 端点 (默认: http://127.0.0.1:8080)
#   ZAP_SCAN_POLICY   - 扫描策略 (默认: High-Medium)
#   CI                 - CI 模式 (github/gitlab, 可选)
# ============================================================

set -euo pipefail

# ── 配置 ──
TARGET_URL="${1:-http://localhost:8000/api}"
SCAN_POLICY="${2:-High-Medium}"
OUTPUT_FILE="${3:-zap-report.json}"
ZAP_ENDPOINT="${ZAP_ENDPOINT:-http://127.0.0.1:8080}"
ZAP_API_KEY="${ZAP_API_KEY:-}"
MAX_WAIT_MINUTES=30

# ── 颜色输出 ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
fail()  { echo -e "${RED}[FAIL]${NC} $1"; }

# ── 前置检查 ──
if [ -z "$ZAP_API_KEY" ]; then
    fail "ZAP_API_KEY 环境变量未设置"
    exit 1
fi

info "============================================"
info "  OWASP ZAP 自动化渗透测试"
info "============================================"
info "目标:     $TARGET_URL"
info "策略:     $SCAN_POLICY"
info "端点:     $ZAP_ENDPOINT"
info "输出:     $OUTPUT_FILE"
info "============================================"

# ── 检查 ZAP 是否运行 ──
info "检查 ZAP 服务状态..."
ZAP_VERSION=$(curl -s -H "X-ZAP-API-Key: $ZAP_API_KEY" \
    "$ZAP_ENDPOINT/JSON/core/view/version/" 2>/dev/null)

if [ -z "$ZAP_VERSION" ]; then
    fail "ZAP 服务未运行在 $ZAP_ENDPOINT"
    fail "请先启动: docker run -d --name zap -p 8080:8080 ghcr.io/zaproxy/zaproxy:stable zap.sh -daemon -port 8080 -host 0.0.0.0 -config api.key=$ZAP_API_KEY"
    exit 1
fi
ok "ZAP 服务运行中"

# ── 启动主动扫描 ──
info "启动主动扫描..."
SCAN_RESPONSE=$(curl -s -H "X-ZAP-API-Key: $ZAP_API_KEY" \
    "$ZAP_ENDPOINT/JSON/ascan/action/scan/" \
    -d "url=$TARGET_URL" \
    -d "scanPolicyName=$SCAN_POLICY" \
    -d "method=GET")

SCAN_ID=$(echo "$SCAN_RESPONSE" | grep -o '"scan":"[^"]*"' | cut -d'"' -f4)

if [ -z "$SCAN_ID" ]; then
    fail "启动扫描失败: $SCAN_RESPONSE"
    exit 1
fi
ok "扫描已启动, ID: $SCAN_ID"

# ── 轮询等待完成 ──
info "等待扫描完成 (最长 ${MAX_WAIT_MINUTES} 分钟)..."
SECONDS=0
PROGRESS=0

while [ $SECONDS -lt $((MAX_WAIT_MINUTES * 60)) ]; do
    sleep 10
    STATUS_RESPONSE=$(curl -s -H "X-ZAP-API-Key: $ZAP_API_KEY" \
        "$ZAP_ENDPOINT/JSON/ascan/view/status/" \
        -d "scanId=$SCAN_ID")
    PROGRESS=$(echo "$STATUS_RESPONSE" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    PROGRESS=${PROGRESS:-0}

    echo -ne "\r${CYAN}  进度: ${PROGRESS}%${NC}    "

    if [ "$PROGRESS" -ge 100 ]; then
        echo ""
        ok "扫描完成!"
        break
    fi
done

if [ "$PROGRESS" -lt 100 ]; then
    echo ""
    warn "扫描超时, 使用当前结果 (${PROGRESS}%)"
fi

# ── 获取告警结果 ──
info "获取扫描结果..."
curl -s -H "X-ZAP-API-Key: $ZAP_API_KEY" \
    "$ZAP_ENDPOINT/JSON/core/view/alerts/?baseurl=$TARGET_URL&start=0&count=1000" \
    -o "$OUTPUT_FILE"

ok "结果已保存到: $OUTPUT_FILE"

# ── 分析结果 ──
TOTAL=$(grep -o '"alert"' "$OUTPUT_FILE" | wc -l)
HIGH=$(grep -o '"risk":"High"' "$OUTPUT_FILE" | wc -l)
MEDIUM=$(grep -o '"risk":"Medium"' "$OUTPUT_FILE" | wc -l)
LOW=$(grep -o '"risk":"Low"' "$OUTPUT_FILE" | wc -l)
INFO=$(grep -o '"risk":"Informational"' "$OUTPUT_FILE" | wc -l)

echo ""
info "============================================"
info "  扫描结果摘要"
info "============================================"
echo -e "  总计:     ${CYAN}$TOTAL${NC}"
echo -e "  High:     ${RED}$HIGH${NC}"
echo -e "  Medium:   ${YELLOW}$MEDIUM${NC}"
echo -e "  Low:      ${GREEN}$LOW${NC}"
echo -e "  Info:     ${CYAN}$INFO${NC}"
echo "============================================"

# ── CI 阻断逻辑 ──
if [ "${CI:-}" = "github" ] || [ "${CI:-}" = "gitlab" ]; then
    if [ "$HIGH" -gt 0 ]; then
        fail "发现 $HIGH 个高危漏洞, 阻断构建!"
        exit 1
    fi
    ok "未发现高危漏洞, 构建通过"
fi

exit 0
