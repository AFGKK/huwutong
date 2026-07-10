#!/bin/bash
# ===================================================
# bootstrap-pgsql.sh — 离线 PG 环境初始化（迁移 + 验证）
# 使用: bash scripts/bootstrap-pgsql.sh
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"

cd "${BASE_DIR}"

if [ ! -f "${COMPOSE_FILE}" ]; then
    echo "[ERROR] 未找到 ${COMPOSE_FILE}"
    exit 1
fi

echo ">>> 运行数据库迁移..."
docker compose -f "${COMPOSE_FILE}" exec -T api php artisan migrate --force

echo ">>> 验证 pgvector..."
docker compose -f "${COMPOSE_FILE}" exec -T postgres \
    psql -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-huwutong}" \
    -c "CREATE EXTENSION IF NOT EXISTS vector;" 2>/dev/null || true

echo ">>> 健康检查..."
docker compose -f "${COMPOSE_FILE}" exec -T api php artisan tinker --execute="echo DB::connection()->getDriverName();" 2>/dev/null || true

if docker compose -f "${COMPOSE_FILE}" exec -T api curl -sf http://localhost:8000/api/health/ready >/dev/null; then
    echo "✅ API ready"
else
    echo "[WARN] API health 未就绪，请检查日志: docker compose -f ${COMPOSE_FILE} logs api"
fi

echo "完成."
