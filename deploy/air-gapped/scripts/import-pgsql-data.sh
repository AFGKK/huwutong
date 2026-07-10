#!/bin/bash
# ===================================================
# import-pgsql-data.sh — 离线环境恢复 PostgreSQL 数据
# 用法: bash scripts/import-pgsql-data.sh [dump文件路径]
# 默认: data/pgsql/huwutong.sql.gz
# 前置: postgres 容器已启动且健康
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
DUMP_FILE="${1:-${BASE_DIR}/data/pgsql/huwutong.sql.gz}"

cd "${BASE_DIR}"

if [ ! -f "${DUMP_FILE}" ]; then
    echo "[ERROR] 未找到数据包: ${DUMP_FILE}"
    exit 1
fi

# 加载 .env
if [ -f ".env" ]; then
    set -a
    # shellcheck disable=SC1091
    source <(grep -E '^[A-Z_]+=' .env | sed 's/\r$//')
    set +a
fi

DB_USERNAME="${DB_USERNAME:-postgres}"
DB_DATABASE="${DB_DATABASE:-huwutong}"

echo ">>> 等待 PostgreSQL 就绪..."
for i in $(seq 1 30); do
    if docker compose -f "${COMPOSE_FILE}" exec -T postgres \
        pg_isready -U "${DB_USERNAME}" -d "${DB_DATABASE}" &>/dev/null; then
        break
    fi
    sleep 2
done

echo ">>> 导入数据: ${DUMP_FILE}"
echo "    目标: ${DB_DATABASE} @ postgres"

gunzip -c "${DUMP_FILE}" | docker compose -f "${COMPOSE_FILE}" exec -T postgres \
    psql -v ON_ERROR_STOP=1 -U "${DB_USERNAME}" -d "${DB_DATABASE}"

echo ">>> 确保 pgvector 扩展..."
docker compose -f "${COMPOSE_FILE}" exec -T postgres \
    psql -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
    -c "CREATE EXTENSION IF NOT EXISTS vector;" 2>/dev/null || true

TABLE_COUNT=$(docker compose -f "${COMPOSE_FILE}" exec -T postgres \
    psql -U "${DB_USERNAME}" -d "${DB_DATABASE}" -tAc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")

echo "✅ 数据恢复完成，当前表数量: ${TABLE_COUNT}"
