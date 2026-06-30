#!/usr/bin/env bash
# ============================================================
# 互物通监控栈部署脚本 (Grafana + Prometheus + Exporters)
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 颜色
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# 命令帮助
usage() {
    echo "用法: $0 {start|stop|restart|status|logs|cleanup}"
    echo ""
    echo "  start   启动所有监控服务"
    echo "  stop    停止所有监控服务"
    echo "  restart 重启所有监控服务"
    echo "  status  查看服务状态"
    echo "  logs    查看日志"
    echo "  cleanup 清理数据卷（⚠️ 会删除所有历史数据）"
    exit 1
}

[ $# -eq 0 ] && usage

CMD="$1"

case "$CMD" in
    start)
        # 检查 .env
        if [ ! -f .env ]; then
            warn ".env 不存在，从 .env.example 复制"
            cp .env.example .env
            info "请编辑 .env 配置后重新运行"
        fi

        info "启动监控栈..."
        docker compose up -d
        info "等待服务启动..."

        # 等待就绪
        sleep 5
        info "Prometheus: http://localhost:9090"
        info "Grafana:    http://localhost:3000 (admin/$(grep GRAFANA_ADMIN_PASSWORD .env | cut -d= -f2))"
        info "cAdvisor:   http://localhost:8080"
        ;;

    stop)
        info "停止监控栈..."
        docker compose down
        ;;

    restart)
        info "重启监控栈..."
        docker compose restart
        ;;

    status)
        docker compose ps
        ;;

    logs)
        docker compose logs -f
        ;;

    cleanup)
        warn "即将删除所有监控数据卷！"
        read -p "确认？(y/N): " confirm
        if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
            docker compose down -v
            info "已清理所有数据卷"
        else
            info "取消"
        fi
        ;;

    *)
        usage
        ;;
esac
