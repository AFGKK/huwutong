#!/bin/bash
# ===================================================
# export-pgsql-data.sh — 导出 PostgreSQL 数据到离线包
# 用法:
#   bash scripts/export-pgsql-data.sh [输出目录]
#   bash scripts/export-pgsql-data.sh deploy/air-gapped/output/pkg/data/pgsql
# 环境: 读取项目根目录 .env（DB_HOST/PORT/DATABASE/USERNAME/PASSWORD）
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
OUT_DIR="${1:-${SCRIPT_DIR}/../data/pgsql}"

mkdir -p "${OUT_DIR}"

# 加载 .env
if [ -f "${PROJECT_ROOT}/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    source <(grep -E '^[A-Z_]+=' "${PROJECT_ROOT}/.env" | sed 's/\r$//')
    set +a
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-huwutong}"
DB_USERNAME="${DB_USERNAME:-postgres}"
DUMP_FILE="${OUT_DIR}/huwutong.sql.gz"
META_FILE="${OUT_DIR}/manifest.txt"

if ! command -v pg_dump &>/dev/null; then
    echo "[ERROR] pg_dump 未安装。Windows 请使用: php scripts/export-pgsql-data.php"
    exit 1
fi

echo ">>> 导出 PostgreSQL: ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
echo ">>> 输出: ${DUMP_FILE}"

export PGPASSWORD="${DB_PASSWORD:-}"
pg_dump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --username="${DB_USERNAME}" \
    --no-owner \
    --no-acl \
    --clean \
    --if-exists \
    "${DB_DATABASE}" | gzip > "${DUMP_FILE}"
unset PGPASSWORD

TABLE_COUNT=$(gunzip -c "${DUMP_FILE}" | grep -c '^CREATE TABLE' || echo "0")
DUMP_SIZE=$(du -h "${DUMP_FILE}" | cut -f1)

cat > "${META_FILE}" << EOF
dumped_at=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
source_host=${DB_HOST}
database=${DB_DATABASE}
username=${DB_USERNAME}
format=sql.gz
tables_create_statements=${TABLE_COUNT}
file_size=${DUMP_SIZE}
git_commit=$(cd "${PROJECT_ROOT}" && git rev-parse HEAD 2>/dev/null || echo 'n/a')
EOF

echo "✅ 导出完成: ${DUMP_FILE} (${DUMP_SIZE}, ~${TABLE_COUNT} tables)"
echo "   元数据: ${META_FILE}"
