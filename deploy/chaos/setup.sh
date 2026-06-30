#!/bin/bash
# ===================================================
# 混沌工程部署脚本
# M3-80 ChaosEngineering
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

CMD="${1:-help}"

case "${CMD}" in
    install)
        info "安装 Chaos Mesh..."
        helm repo add chaos-mesh https://charts.chaos-mesh.org 2>/dev/null || true
        helm repo update
        helm upgrade --install chaos-mesh chaos-mesh/chaos-mesh \
            --namespace=chaos-engineering --create-namespace \
            --set chaosDaemon.runtime=containerd \
            --set chaosDaemon.socketPath=/run/containerd/containerd.sock
        info "等待 Chaos Mesh 启动..."
        kubectl wait --for=condition=Ready pods --all -n chaos-engineering --timeout=120s
        info "✅ Chaos Mesh 已安装"
        ;;

    apply)
        info "部署混沌实验..."
        for f in "${SCRIPT_DIR}"/*.yaml; do
            [ -f "$f" ] || continue
            info "  应用: $(basename "$f")"
            kubectl apply -f "$f" 2>/dev/null || warn "  跳过: $(basename "$f")"
        done
        info "✅ 实验已部署"
        kubectl get chaos -A
        ;;

    status)
        info "查看混沌实验状态..."
        echo ""
        echo "--- Chaos Mesh Dashboard ---"
        kubectl get svc -n chaos-engineering chaos-dashboard -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "  Chaos Mesh Dashboard: kubectl port-forward -n chaos-engineering svc/chaos-dashboard 2333:2333"
        echo ""
        echo "--- 活跃实验 ---"
        kubectl get chaos -A 2>/dev/null || echo "  未发现混沌实验"
        echo ""
        echo "--- 受影响的 Pod ---"
        kubectl get pods -l chaos-mesh.org/inject=yes -A 2>/dev/null || echo "  无受影响 Pod"
        ;;

    cleanup)
        warn "清理所有混沌实验..."
        kubectl delete chaos -A --all 2>/dev/null || true
        info "✅ 已清理所有实验"
        ;;

    dashboard)
        info "打开 Chaos Mesh Dashboard..."
        kubectl port-forward -n chaos-engineering svc/chaos-dashboard 2333:2333 &
        echo "  访问: http://localhost:2333"
        ;;

    *)
        echo "使用方式: $0 {install|apply|status|cleanup|dashboard}"
        echo ""
        echo "  install   安装 Chaos Mesh"
        echo "  apply     部署所有实验 YAML"
        echo "  status    查看实验状态"
        echo "  cleanup   清理所有实验"
        echo "  dashboard 打开 Chaos Mesh Dashboard"
        ;;
esac
