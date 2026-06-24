#!/bin/bash
# ===================================================
# deploy-offline.sh
# 离线环境部署脚本
# 使用方式: bash scripts/deploy-offline.sh
# 说明: 在完全无互联网的环境中加载 Docker 镜像
#       并启动 HWT License 系统
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "=========================================="
echo " HWT License 离线部署"
echo "=========================================="

# ---------- 前置检查 ----------
if ! command -v docker &>/dev/null; then
    echo "[ERROR] Docker 未安装"
    echo "请先在离线环境安装 Docker:"
    echo "  - 从 U 盘获取 docker.deb / docker.rpm"
    echo "  - 或访问 https://docs.docker.com/engine/install/"
    exit 1
fi

if ! command -v docker &>/dev/null; then
    echo "[ERROR] Docker Compose 未安装"
    exit 1
fi

echo ""
echo ">>> [1/6] 检查 Docker 运行状态..."
if ! docker info &>/dev/null; then
    echo "[ERROR] Docker 守护进程未运行"
    echo "请执行: systemctl start docker 或 service docker start"
    exit 1
fi
echo "  Docker 正常运行: $(docker --version)"

echo ""
echo ">>> [2/6] 校验离线包完整性..."
if [ -f "${BASE_DIR}/SHA256SUMS" ]; then
    cd "${BASE_DIR}"
    sha256sum -c SHA256SUMS --quiet 2>/dev/null && {
        echo "  ✅ SHA256 校验通过"
    } || {
        echo "  [WARN] SHA256 校验失败，可能文件已损坏"
        echo "  是否继续? (y/N)"
        read -r continue_choice
        if [ "${continue_choice}" != "y" ] && [ "${continue_choice}" != "Y" ]; then
            exit 1
        fi
    }
    cd "${SCRIPT_DIR}"
else
    echo "  [WARN] 未找到 SHA256SUMS 文件，跳过校验"
fi

echo ""
echo ">>> [3/6] 加载 Docker 镜像..."
IMAGE_DIR="${BASE_DIR}/docker-images"
if [ -d "${IMAGE_DIR}" ]; then
    img_count=$(ls "${IMAGE_DIR}"/*.tar 2>/dev/null | wc -l)
    echo "  发现 ${img_count} 个镜像文件，开始加载..."

    for tar_file in "${IMAGE_DIR}"/*.tar; do
        [ -f "${tar_file}" ] || continue
        img_name=$(basename "${tar_file}" .tar)
        echo "    Loading ${img_name}..."
        docker load -i "${tar_file}" || echo "    [ERROR] 加载 ${img_name} 失败"
    done

    echo "  当前所有 Docker 镜像:"
    docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}"
else
    echo "  [ERROR] 未找到 docker-images 目录"
    exit 1
fi

echo ""
echo ">>> [4/6] 检查端口占用..."
PORTS=(8000 3306 6379 8080)
for port in "${PORTS[@]}"; do
    if lsof -i:${port} &>/dev/null 2>/dev/null || netstat -tuln 2>/dev/null | grep -q ":${port} "; then
        echo "  [WARN] 端口 ${port} 已被占用，请检查"
    else
        echo "  端口 ${port} 可用"
    fi
done

echo ""
echo ">>> [5/6] 配置环境变量..."
if [ -f "${BASE_DIR}/.env" ]; then
    # 检查是否需要修改配置
    if [ ! -f "${BASE_DIR}/.env.airgap" ]; then
        cp "${BASE_DIR}/.env" "${BASE_DIR}/.env.airgap"
    fi
    echo "  环境变量已加载"
else
    echo "  [ERROR] 未找到 .env 文件"
    exit 1
fi

echo ""
echo ">>> [6/6] 启动服务..."
cd "${BASE_DIR}"

# 如果有 docker-compose.yml 则直接启动
if [ -f "docker-compose.yml" ]; then
    docker compose -f docker-compose.yml up -d
    echo ""
    echo "  服务启动中，等待10秒..."
    sleep 10

    # 检查服务状态
    docker compose ps
else
    # 手动启动容器
    echo "  docker-compose.yml 不存在，手动启动容器..."
    echo "  请参考 README.md 手动配置"
fi

cd "${SCRIPT_DIR}"

echo ""
echo "=========================================="
echo " ✅ 离线部署完成!"
echo ""
echo " 访问地址:"
echo "   Web:  http://localhost:8000"
echo "   API:  http://localhost:8000/api"
echo "   Reverb: http://localhost:8080"
echo ""
echo " 管理命令:"
echo "   查看日志:  docker compose logs -f"
echo "   停止:      docker compose down"
echo "   重启:      docker compose restart"
echo ""
echo " License 导入:"
echo "   bash scripts/import-license.sh /path/to/license.lic"
echo "=========================================="
