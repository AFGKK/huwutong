#!/bin/bash
# ===================================================
# create-update-package.sh — 生成离线增量更新包
# 用法: bash scripts/create-update-package.sh <版本号>
# 示例: bash scripts/create-update-package.sh 1.2.0
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
VERSION="${1:-}"

if [ -z "${VERSION}" ]; then
    echo "用法: $0 <版本号>  例: $0 1.2.0"
    exit 1
fi

OUTPUT="${BASE_DIR}/updates/hwt-update-v${VERSION}.update.tar.gz"
TMP_DIR=$(mktemp -d)
trap 'rm -rf "${TMP_DIR}"' EXIT

mkdir -p "${TMP_DIR}/docker-images" "${TMP_DIR}/scripts" "${TMP_DIR}/database/migrations"

echo ">>> 构建 API 镜像 v${VERSION}..."
DOCKERFILE="${PROJECT_ROOT}/deploy/docker/Dockerfile"
docker build -f "${DOCKERFILE}" \
    -t "hwt-license-api:${VERSION}" \
    -t "hwt-license-api:latest" \
    "${PROJECT_ROOT}"

echo ">>> 导出镜像..."
docker save "hwt-license-api:${VERSION}" -o "${TMP_DIR}/docker-images/hwt-api.tar"

echo ">>> 复制数据库迁移..."
cp -r "${PROJECT_ROOT}/database/migrations/." "${TMP_DIR}/database/migrations/"

echo "${VERSION}" > "${TMP_DIR}/VERSION"
git -C "${PROJECT_ROOT}" log -1 --oneline > "${TMP_DIR}/GIT_COMMIT" 2>/dev/null || true

cat > "${TMP_DIR}/scripts/pre-update.sh" << 'PRE'
#!/bin/bash
set -euo pipefail
echo "[pre-update] 备份当前数据库..."
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
if [ -f .env ]; then
    set -a
    source <(grep -E '^[A-Z_]+=' .env | sed 's/\r$//')
    set +a
fi
if grep -q '^DB_CONNECTION=pgsql' .env 2>/dev/null; then
    mkdir -p backups
    docker compose -f "${COMPOSE_FILE}" exec -T postgres \
        pg_dump -U "${DB_USERNAME:-postgres}" "${DB_DATABASE:-huwutong}" \
        | gzip > "backups/pre-update-$(date +%Y%m%d_%H%M%S).sql.gz" || true
    echo "[pre-update] PG 备份完成"
elif grep -q '^DB_CONNECTION=mysql' .env 2>/dev/null; then
    mkdir -p backups
    docker compose -f "${COMPOSE_FILE:-docker-compose.mysql.yml}" exec -T mysql \
        mysqldump -u root -p"${DB_PASSWORD:-HwtRoot2024!}" \
        --single-transaction --quick --skip-lock-tables \
        "${DB_DATABASE:-huwutong}" \
        | gzip > "backups/pre-update-$(date +%Y%m%d_%H%M%S).sql.gz" || true
    echo "[pre-update] MySQL 备份完成"
fi
PRE

cat > "${TMP_DIR}/scripts/post-update.sh" << 'POST'
#!/bin/bash
set -euo pipefail
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
echo "[post-update] 运行数据库迁移..."
docker compose -f "${COMPOSE_FILE}" exec -T api php artisan migrate --force
docker compose -f "${COMPOSE_FILE}" exec -T api php artisan config:cache 2>/dev/null || true
echo "[post-update] 健康检查..."
curl -sf http://localhost:8000/api/health/ready || echo "[WARN] health/ready 未通过"
POST

chmod +x "${TMP_DIR}/scripts/"*.sh

cd "${TMP_DIR}"
find . -type f ! -name 'SHA256SUMS' -exec sha256sum {} \; > SHA256SUMS
tar -czf "${OUTPUT}" -C "${TMP_DIR}" .

echo "✅ 更新包已生成: ${OUTPUT}"
echo "   应用: bash scripts/apply-update.sh ${OUTPUT}"
