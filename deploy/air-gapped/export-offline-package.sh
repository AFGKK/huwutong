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
OUTPUT_DIR="${SCRIPT_DIR}/output/hwt-license-offline-${VERSION}"
PACKAGE_NAME="hwt-license-offline-${VERSION}.zip"

echo "=========================================="
echo " HWT License 离线安装包导出"
echo " 版本: ${VERSION}"
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
    "mysql:8.0"
    "redis:7-alpine"
    "nginx:1.25-alpine"
    "composer:2.6"
    "node:20-alpine"
)

for img in "${IMAGES[@]}"; do
    echo "  Pulling ${img}..."
    docker pull "${img}" 2>/dev/null || echo "  [WARN] Please pull ${img} manually"
done

echo ""
echo ">>> [2/5] Building application Docker images..."

# Build API image
docker build -f "${PROJECT_ROOT}/Dockerfile" \
    --build-arg APP_ENV=production \
    --build-arg APP_DEBUG=false \
    -t "hwt-license-api:${VERSION}" \
    -t "hwt-license-api:latest" \
    "${PROJECT_ROOT}" 2>/dev/null || echo "  [WARN] Dockerfile not found, building minimal image"

# If no Dockerfile, create a minimal one
if [ ! -f "${PROJECT_ROOT}/Dockerfile" ]; then
    cat > /tmp/hwt-minimal.Dockerfile << 'DOCKERFILE'
FROM php:8.3-fpm-alpine
RUN apk add --no-cache nginx supervisor
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

# Copy docker-compose
cp "${SCRIPT_DIR}/docker-compose.yml" "${OUTPUT_DIR}/" 2>/dev/null || true
cp "${SCRIPT_DIR}/.env.airgap" "${OUTPUT_DIR}/.env" 2>/dev/null || true
cp "${SCRIPT_DIR}/config/"* "${OUTPUT_DIR}/config/" 2>/dev/null || true

# Create manifest
cat > "${OUTPUT_DIR}/MANIFEST" << MANIFEST
Package Name: ${PACKAGE_NAME}
Version: ${VERSION}
Created: $(date -u '+%Y-%m-%dT%H:%M:%SZ')
Git Commit: $(git rev-parse HEAD 2>/dev/null || echo 'n/a')
Git Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'n/a')
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
echo "     bash scripts/deploy-offline.sh"
echo "=========================================="
