#!/bin/bash
# ===================================================
# export-mysql-data.sh — 导出 MySQL 数据到离线包（遗留栈）
# 用法:
#   bash scripts/export-mysql-data.sh [输出目录]
#   bash scripts/export-mysql-data.sh deploy/air-gapped/output/pkg/data/mysql
# 环境: 读取项目根目录 .env（DB_HOST/PORT/DATABASE/USERNAME/PASSWORD）
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
OUT_DIR="${1:-${SCRIPT_DIR}/../data/mysql}"

mkdir -p "${OUT_DIR}"

if [ -f "${PROJECT_ROOT}/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    source <(grep -E '^[A-Z_]+=' "${PROJECT_ROOT}/.env" | sed 's/\r$//')
    set +a
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-huwutong}"
DB_USERNAME="${DB_USERNAME:-root}"
DUMP_FILE="${OUT_DIR}/huwutong.sql.gz"
META_FILE="${OUT_DIR}/manifest.txt"
MYSQLDUMP="${MYSQLDUMP_PATH:-mysqldump}"

if ! command -v "${MYSQLDUMP}" &>/dev/null; then
    echo "[ERROR] mysqldump 未安装。Windows 请使用: php scripts/export-mysql-data.php"
    exit 1
fi

echo ">>> 导出 MySQL: ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
echo ">>> 输出: ${DUMP_FILE}"

"${MYSQLDUMP}" \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USERNAME}" \
    --password="${DB_PASSWORD:-}" \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    --routines \
    --events \
    --add-drop-table \
    --set-gtid-purged=OFF \
    "${DB_DATABASE}" | gzip > "${DUMP_FILE}"

TABLE_COUNT=$(gunzip -c "${DUMP_FILE}" | grep -c '^CREATE TABLE' || echo "0")
DUMP_SIZE=$(du -h "${DUMP_FILE}" | cut -f1)

cat > "${META_FILE}" << EOF
dumped_at=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
engine=mysql
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
