#!/bin/bash
# ===================================================
# 蓝绿部署管理脚本
# M3-63 BlueGreenDeploy
# ===================================================
set -euo pipefail

NAMESPACE="${NAMESPACE:-huwutong}"
SERVICE="hwt-api"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

show_status() {
    local env
    env=$(kubectl get service "${SERVICE}" -n "${NAMESPACE}" -o jsonpath='{.spec.selector.deployment}' 2>/dev/null || echo "unknown")
    echo "当前活跃环境: ${env}"
    echo ""
    echo "--- Blue ---"
    kubectl get pods -n "${NAMESPACE}" -l deployment=blue 2>/dev/null || echo "  无 Pod"
    echo ""
    echo "--- Green ---"
    kubectl get pods -n "${NAMESPACE}" -l deployment=green 2>/dev/null || echo "  无 Pod"
}

CMD="${1:-help}"

case "${CMD}" in
    status)
        show_status
        ;;
    deploy)
        info "部署新版本到 Green..."
        kubectl set image deployment/hwt-api-green -n "${NAMESPACE}" api="${IMAGE:-hwt-license-api:latest}"
        kubectl rollout status deployment/hwt-api-green -n "${NAMESPACE}" --timeout=300s
        info "✅ Green 部署完成"
        ;;
    switch)
        TARGET="${2:-}"
        [ -z "${TARGET}" ] && { error "请指定目标: blue 或 green"; exit 1; }
        info "切换流量到 ${TARGET}..."
        kubectl patch service "${SERVICE}" -n "${NAMESPACE}" -p "{\"spec\":{\"selector\":{\"deployment\":\"${TARGET}\"}}}"
        info "✅ 流量已切换到 ${TARGET}"
        ;;
    rollback)
        CURRENT=$(kubectl get service "${SERVICE}" -n "${NAMESPACE}" -o jsonpath='{.spec.selector.deployment}')
        TARGET=$([ "${CURRENT}" = "blue" ] && echo "green" || echo "blue")
        info "从 ${CURRENT} 回滚到 ${TARGET}..."
        kubectl patch service "${SERVICE}" -n "${NAMESPACE}" -p "{\"spec\":{\"selector\":{\"deployment\":\"${TARGET}\"}}}"
        info "✅ 已回滚到 ${TARGET}"
        ;;
    apply)
        info "应用 Blue/Green 部署清单..."
        kubectl apply -k deploy/blue-green/
        info "✅ 清单已应用"
        show_status
        ;;
    test)
        info "在 Green 环境运行健康检查..."
        GREEN_POD=$(kubectl get pods -n "${NAMESPACE}" -l deployment=green -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        if [ -n "${GREEN_POD}" ]; then
            kubectl exec -n "${NAMESPACE}" "${GREEN_POD}" -- curl -sf http://localhost:8000/api/health && \
                info "✅ Green 健康检查通过" || error "❌ Green 健康检查失败"
        else
            error "Green 环境无运行 Pod"
        fi
        ;;
    *)
        echo "使用方式: $0 {status|deploy|switch|rollback|apply|test}"
        echo ""
        echo "  status             查看当前活跃环境和 Pod 状态"
        echo "  deploy             部署新版本到 Green"
        echo "  switch <blue|green> 切换流量到指定环境"
        echo "  rollback           回滚到另一个环境"
        echo "  apply              应用 K8s 清单"
        echo "  test               对 Green 环境运行健康检查"
        ;;
esac
