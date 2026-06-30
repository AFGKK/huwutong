# ===================================================
# 混沌工程部署清单
# M3-80 ChaosEngineering
# ===================================================

# ─── 前置条件 ───
# 1. 已安装 Chaos Mesh (v2.7+)
#    helm repo add chaos-mesh https://charts.chaos-mesh.org
#    helm install chaos-mesh chaos-mesh/chaos-mesh --namespace=chaos-engineering --create-namespace
#
# 2. 已安装 kubectl 并配置 K8s 上下文
#
# 3. 已启用混沌工程配置:
#    CHAOS_ENGINEERING_ENABLED=true
#    CHAOS_PROVIDER=chaos_mesh

# ─── 快速开始 ───
# kubectl apply -f deploy/chaos/

echo "=========================================="
echo " 混沌工程部署助手"
echo " M3-80 ChaosEngineering"
echo "=========================================="
echo ""
echo "使用方式:"
echo "  bash deploy/chaos/setup.sh install    # 安装 Chaos Mesh"
echo "  bash deploy/chaos/setup.sh apply      # 部署故障实验"
echo "  bash deploy/chaos/setup.sh status     # 查看实验状态"
echo "  bash deploy/chaos/setup.sh cleanup    # 清理所有实验"
echo ""
echo "实验类型:"
echo "  1. Redis 宕机 (redis-outage.yaml)"
echo "  2. DB 主从切换 (db-failover.yaml)"
echo "  3. Pod 随机 Kill (pod-kill.yaml)"
echo "  4. 网络延迟 (network-latency.yaml)"
echo "  5. 磁盘满载 (disk-full.yaml)"
echo "  6. CPU 压力 (cpu-stress.yaml)"
echo "=========================================="
