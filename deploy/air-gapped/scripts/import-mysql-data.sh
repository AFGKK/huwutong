#!/bin/bash
# ===================================================
# import-mysql-data.sh — 离线环境恢复 MySQL 数据（遗留栈）
# 用法: bash scripts/import-mysql-data.sh [dump文件路径]
# 默认: data/mysql/huwutong.sql.gz
# 前置: mysql 容器已启动且健康
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.mysql.yml}"
DUMP_FILE="${1:-${BASE_DIR}/data/mysql/huwutong.sql.gz}"

cd "${BASE_DIR}"

if [ ! -f "${DUMP_FILE}" ]; then
    echo "[ERROR] 未找到数据包: ${DUMP_FILE}"
    exit 1
fi

if [ -f ".env" ]; then
    set -a
    # shellcheck disable=SC1091
    source <(grep -E '^[A-Z_]+=' .env | sed 's/\r$//')
    set +a
fi

DB_ROOT_PASSWORD="${DB_PASSWORD:-HwtRoot2024!}"
DB_DATABASE="${DB_DATABASE:-huwutong}"

echo ">>> 等待 MySQL 就绪..."
for i in $(seq 1 30); do
    if docker compose -f "${COMPOSE_FILE}" exec -T mysql \
        mysqladmin ping -h localhost -u root -p"${DB_ROOT_PASSWORD}" &>/dev/null; then
        break
    fi
    sleep 2
done

echo ">>> 导入数据: ${DUMP_FILE}"
echo "    目标: ${DB_DATABASE} @ mysql"

gunzip -c "${DUMP_FILE}" | docker compose -f "${COMPOSE_FILE}" exec -T mysql \
    mysql --binary-mode=1 -u root -p"${DB_ROOT_PASSWORD}" "${DB_DATABASE}"

TABLE_COUNT=$(docker compose -f "${COMPOSE_FILE}" exec -T mysql \
    mysql -u root -p"${DB_ROOT_PASSWORD}" -Nse \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}' AND table_type='BASE TABLE'")

echo "✅ 数据恢复完成，当前表数量: ${TABLE_COUNT}"
