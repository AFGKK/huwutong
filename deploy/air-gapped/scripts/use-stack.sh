#!/bin/bash
# ===================================================
# use-stack.sh — 切换离线包数据库栈
# 用法: bash scripts/use-stack.sh pgsql|mysql
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
STACK="${1:-}"

if [ -z "${STACK}" ]; then
    echo "用法: $0 pgsql|mysql"
    echo ""
    echo "  pgsql  → docker-compose.yml + .env.airgap（默认，推荐）"
    echo "  mysql  → docker-compose.mysql.yml + .env.airgap.mysql（遗留）"
    exit 1
fi

cd "${BASE_DIR}"

case "${STACK}" in
    pgsql|pg|postgres|postgresql)
        ENV_SRC=".env.airgap"
        [ -f ".env.airgap.pgsql" ] && ENV_SRC=".env.airgap.pgsql"
        COMPOSE="docker-compose.yml"
        ;;
    mysql|my)
        ENV_SRC=".env.airgap.mysql"
        COMPOSE="docker-compose.mysql.yml"
        ;;
    *)
        echo "[ERROR] 未知栈: ${STACK}（支持 pgsql | mysql）"
        exit 1
        ;;
esac

if [ ! -f "${ENV_SRC}" ]; then
    echo "[ERROR] 未找到 ${ENV_SRC}"
    exit 1
fi

cp "${ENV_SRC}" .env

case "${STACK}" in
    mysql|my)
        echo "mysql" > STACK.default
        ;;
    *)
        echo "pgsql" > STACK.default
        ;;
esac

echo "✅ 已切换为 ${STACK} 栈"
echo "   环境: .env ← ${ENV_SRC}"
echo "   启动: docker compose -f ${COMPOSE} up -d"
echo "   或:   DEPLOY_STACK=${STACK} bash scripts/deploy-offline.sh"
