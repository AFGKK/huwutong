#!/bin/bash
# ===================================================
# export-offline-package.sh
# HWT License 离线安装包导出脚本
# 使用方式: bash export-offline-package.sh [版本号]
# 说明: 在联网开发环境执行，导出所有 Docker 镜像
#       和应用代码离线安装包
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
VERSION="${1:-$(date +%Y%m%d)-$(git describe --tags --always 2>/dev/null || echo 'unknown')}"
STACK="${2:-pgsql}"  # pgsql（默认）| mysql | both
INCLUDE_DATA="${INCLUDE_DATA:-0}"
if [ "${3:-}" = "with-data" ]; then
    INCLUDE_DATA=1
fi
OUTPUT_DIR="${SCRIPT_DIR}/output/hwt-license-offline-${VERSION}"
PACKAGE_NAME="hwt-license-offline-${VERSION}.zip"

echo "=========================================="
echo " HWT License 离线安装包导出"
echo " 版本: ${VERSION}"
echo " 数据库栈: ${STACK}"
echo "=========================================="

# ---------- 检查环境 ----------
if ! command -v docker &>/dev/null; then
    echo "[ERROR] Docker 未安装"
    exit 1
fi

# ---------- 创建输出目录 ----------
rm -rf "${OUTPUT_DIR}"
mkdir -p "${OUTPUT_DIR}"/{docker-images,scripts,config,updates}

echo ""
echo ">>> [1/5] Pulling Docker images..."

IMAGES=(
    "redis:7-alpine"
    "nginx:1.25-alpine"
    "composer:2.6"
    "node:20-alpine"
)

case "${STACK}" in
    mysql)
        IMAGES+=("mysql:8.0")
        ;;
    pgsql)
        IMAGES+=("pgvector/pgvector:pg16")
        ;;
    *)
        IMAGES+=("pgvector/pgvector:pg16" "mysql:8.0")
        ;;
esac

for img in "${IMAGES[@]}"; do
    echo "  Pulling ${img}..."
    docker pull "${img}" 2>/dev/null || echo "  [WARN] Please pull ${img} manually"
done

echo ""
echo ">>> [2/5] Building application Docker images..."

# Build API image (含 pdo_mysql + pdo_pgsql)
DOCKERFILE="${PROJECT_ROOT}/deploy/docker/Dockerfile"
if [ -f "${DOCKERFILE}" ]; then
    docker build -f "${DOCKERFILE}" \
        -t "hwt-license-api:${VERSION}" \
        -t "hwt-license-api:latest" \
        "${PROJECT_ROOT}"
elif [ -f "${PROJECT_ROOT}/Dockerfile" ]; then
    docker build -f "${PROJECT_ROOT}/Dockerfile" \
        --build-arg APP_ENV=production \
        --build-arg APP_DEBUG=false \
        -t "hwt-license-api:${VERSION}" \
        -t "hwt-license-api:latest" \
        "${PROJECT_ROOT}"
else
    echo "  [WARN] Dockerfile not found, building minimal image"
    cat > /tmp/hwt-minimal.Dockerfile << 'DOCKERFILE'
FROM php:8.3-fpm-alpine
RUN apk add --no-cache nginx supervisor postgresql-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql
COPY . /app
DOCKERFILE
    docker build -f /tmp/hwt-minimal.Dockerfile -t "hwt-license-api:${VERSION}" "${PROJECT_ROOT}"
fi

echo ""
echo ">>> [3/5] Exporting Docker images to tar files..."

# Export app image
docker save "hwt-license-api:${VERSION}" -o "${OUTPUT_DIR}/docker-images/hwt-api.tar" 2>/dev/null
docker save "hwt-license-api:latest" -o "${OUTPUT_DIR}/docker-images/hwt-api-latest.tar" 2>/dev/null

# Export dependency images (skip if not present, will be pulled on target)
for img in "${IMAGES[@]}"; do
    safe_name=$(echo "${img}" | tr '/:' '_')
    docker save "${img}" -o "${OUTPUT_DIR}/docker-images/${safe_name}.tar" 2>/dev/null || true
done

echo ""
echo ">>> [4/5] Copying scripts and configs..."

# Copy scripts
cp "${SCRIPT_DIR}/scripts/"*.sh "${OUTPUT_DIR}/scripts/" 2>/dev/null || true
cp "${PROJECT_ROOT}/scripts/export-pgsql-data.php" "${OUTPUT_DIR}/scripts/" 2>/dev/null || true
cp "${PROJECT_ROOT}/scripts/export-mysql-data.php" "${OUTPUT_DIR}/scripts/" 2>/dev/null || true

# Copy compose & env templates
cp "${SCRIPT_DIR}/docker-compose.yml" "${OUTPUT_DIR}/" 2>/dev/null || true
cp "${SCRIPT_DIR}/docker-compose.pgsql.yml" "${OUTPUT_DIR}/" 2>/dev/null || true
cp "${SCRIPT_DIR}/docker-compose.mysql.yml" "${OUTPUT_DIR}/" 2>/dev/null || true
cp "${SCRIPT_DIR}/.env.airgap" "${OUTPUT_DIR}/.env" 2>/dev/null || true
cp "${SCRIPT_DIR}/.env.airgap.pgsql" "${OUTPUT_DIR}/" 2>/dev/null || true
cp "${SCRIPT_DIR}/.env.airgap.mysql" "${OUTPUT_DIR}/" 2>/dev/null || true
cp -r "${SCRIPT_DIR}/config/"* "${OUTPUT_DIR}/config/" 2>/dev/null || true
echo "pgsql" > "${OUTPUT_DIR}/STACK.default"

# 可选: 打包 PostgreSQL 数据
if [ "${INCLUDE_DATA}" = "1" ] && { [ "${STACK}" = "pgsql" ] || [ "${STACK}" = "both" ]; }; then
    echo ""
    echo ">>> [4.5/5] 导出 PostgreSQL 数据..."
    if bash "${SCRIPT_DIR}/scripts/export-pgsql-data.sh" "${OUTPUT_DIR}/data/pgsql"; then
        echo "  数据已写入 data/pgsql/"
    else
        echo "  [WARN] PG 数据导出失败，尝试: php scripts/export-pgsql-data.php"
        php "${PROJECT_ROOT}/scripts/export-pgsql-data.php" "${OUTPUT_DIR}/data/pgsql" 2>/dev/null || \
            echo "  [WARN] 跳过 PG 数据打包"
    fi
fi

# 可选: 打包 MySQL 数据（遗留栈）
if [ "${INCLUDE_DATA}" = "1" ] && { [ "${STACK}" = "mysql" ] || [ "${STACK}" = "both" ]; }; then
    echo ""
    echo ">>> [4.6/5] 导出 MySQL 数据..."
    if bash "${SCRIPT_DIR}/scripts/export-mysql-data.sh" "${OUTPUT_DIR}/data/mysql"; then
        echo "  数据已写入 data/mysql/"
    else
        echo "  [WARN] MySQL 数据导出失败，尝试: php scripts/export-mysql-data.php"
        php "${PROJECT_ROOT}/scripts/export-mysql-data.php" "${OUTPUT_DIR}/data/mysql" 2>/dev/null || \
            echo "  [WARN] 跳过 MySQL 数据打包"
    fi
fi

# Create manifest
cat > "${OUTPUT_DIR}/MANIFEST" << MANIFEST
Package Name: ${PACKAGE_NAME}
Version: ${VERSION}
Created: $(date -u '+%Y-%m-%dT%H:%M:%SZ')
Git Commit: $(git rev-parse HEAD 2>/dev/null || echo 'n/a')
Git Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'n/a')
Database Stack: ${STACK}
Default Stack: pgsql (PostgreSQL 16 + pgvector)
Includes DB Data: ${INCLUDE_DATA}
Compose (PG): docker-compose.yml / docker-compose.pgsql.yml
Legacy MySQL: docker-compose.mysql.yml + .env.airgap.mysql
Images:
$(ls "${OUTPUT_DIR}/docker-images/" 2>/dev/null | sed 's/^/  - /')
MANIFEST

# Generate SHA256SUMS
cd "${OUTPUT_DIR}"
find . -type f ! -name 'SHA256SUMS' -exec sha256sum {} \; > SHA256SUMS
cd "${SCRIPT_DIR}"

echo ""
echo ">>> [5/5] Creating offline package zip..."

cd "${SCRIPT_DIR}/output"
zip -r "${PACKAGE_NAME}" "hwt-license-offline-${VERSION}/" -x "*/node_modules/*" "*/vendor/*" "*/.*"
cd "${SCRIPT_DIR}"

# Final output
PACKAGE_PATH="${SCRIPT_DIR}/output/${PACKAGE_NAME}"
PACKAGE_SIZE=$(du -h "${PACKAGE_PATH}" | cut -f1)

echo ""
echo "=========================================="
echo " ✅ 离线安装包导出完成!"
echo "    文件: ${PACKAGE_PATH}"
echo "    大小: ${PACKAGE_SIZE}"
echo "    目录: ${OUTPUT_DIR}"
echo "=========================================="
echo ""
echo "下一步:"
echo "  1. 将 ${PACKAGE_NAME} 拷贝到 U 盘/光盘"
echo "  2. 在目标离线环境执行:"
echo "     unzip ${PACKAGE_NAME}"
echo "     cd hwt-license-offline-${VERSION}"
echo "     bash scripts/check-integrity.sh"
echo "     bash scripts/deploy-offline.sh          # 默认 PostgreSQL"
echo "     DEPLOY_STACK=mysql bash scripts/deploy-offline.sh  # MySQL 遗留"
echo ""
echo "  含数据导出: bash export-offline-package.sh ${VERSION} pgsql with-data"
echo "  MySQL 遗留: bash export-offline-package.sh ${VERSION} mysql with-data"
echo "  或 Windows: php scripts/export-pgsql-data.php / php scripts/export-mysql-data.php"
echo "=========================================="
