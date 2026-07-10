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

# 栈选择: pgsql（默认）| mysql
DEPLOY_STACK="${DEPLOY_STACK:-}"
if [ -z "${DEPLOY_STACK}" ] && [ -f "${BASE_DIR}/STACK.default" ]; then
    DEPLOY_STACK="$(tr -d '\r\n' < "${BASE_DIR}/STACK.default")"
fi
DEPLOY_STACK="${DEPLOY_STACK:-pgsql}"

if [ -f "${BASE_DIR}/.env" ] && grep -q '^DB_CONNECTION=mysql' "${BASE_DIR}/.env" 2>/dev/null; then
    DEPLOY_STACK="mysql"
fi

case "${DEPLOY_STACK}" in
    mysql)
        COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.mysql.yml}"
        ENV_TEMPLATE=".env.airgap.mysql"
        ;;
    pgsql|pg|postgres)
        COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
        ENV_TEMPLATE=".env.airgap"
        DEPLOY_STACK="pgsql"
        ;;
    *)
        echo "[ERROR] 未知 DEPLOY_STACK=${DEPLOY_STACK}（支持 pgsql | mysql）"
        exit 1
        ;;
esac

echo "=========================================="
echo " HWT License 离线部署 (${DEPLOY_STACK})"
echo " Compose: ${COMPOSE_FILE}"
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
if [ "${DEPLOY_STACK}" = "mysql" ]; then
    PORTS=(8000 3306 6379 80 443)
else
    PORTS=(8000 5432 6379 80 443)
fi
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
    if [ ! -f "${BASE_DIR}/.env.airgap" ]; then
        cp "${BASE_DIR}/.env" "${BASE_DIR}/.env.airgap"
    fi
    echo "  环境变量已加载 (.env)"
elif [ -f "${BASE_DIR}/${ENV_TEMPLATE}" ]; then
    cp "${BASE_DIR}/${ENV_TEMPLATE}" "${BASE_DIR}/.env"
    echo "  已从 ${ENV_TEMPLATE} 生成 .env"
else
    echo "  [ERROR] 未找到 .env 或 ${ENV_TEMPLATE}"
    exit 1
fi

echo ""
echo ">>> [6/6] 启动服务..."
cd "${BASE_DIR}"

DATA_DUMP_PGSQL="${BASE_DIR}/data/pgsql/huwutong.sql.gz"
DATA_DUMP_MYSQL="${BASE_DIR}/data/mysql/huwutong.sql.gz"
IMPORT_DATA="${IMPORT_DB_DATA:-auto}"

if [ -f "${COMPOSE_FILE}" ]; then
    # 含数据包时先启动数据库，导入后再拉起全栈
    if [ "${DEPLOY_STACK}" = "pgsql" ] && [ -f "${DATA_DUMP_PGSQL}" ] && [ "${IMPORT_DATA}" != "skip" ]; then
        if [ "${IMPORT_DATA}" = "auto" ] || [ "${IMPORT_DATA}" = "1" ]; then
            echo ""
            echo "  检测到 PG 数据包，先启动 postgres + redis..."
            docker compose -f "${COMPOSE_FILE}" up -d postgres redis
            echo "  等待 PostgreSQL 就绪..."
            sleep 10
            echo "  导入业务数据..."
            COMPOSE_FILE="${COMPOSE_FILE}" bash scripts/import-pgsql-data.sh "${DATA_DUMP_PGSQL}"
            echo "  启动全部服务..."
        fi
    fi

    if [ "${DEPLOY_STACK}" = "mysql" ] && [ -f "${DATA_DUMP_MYSQL}" ] && [ "${IMPORT_DATA}" != "skip" ]; then
        if [ "${IMPORT_DATA}" = "auto" ] || [ "${IMPORT_DATA}" = "1" ]; then
            echo ""
            echo "  检测到 MySQL 数据包，先启动 mysql + redis..."
            docker compose -f "${COMPOSE_FILE}" up -d mysql redis
            echo "  等待 MySQL 就绪..."
            sleep 15
            echo "  导入业务数据..."
            COMPOSE_FILE="${COMPOSE_FILE}" bash scripts/import-mysql-data.sh "${DATA_DUMP_MYSQL}"
            echo "  启动全部服务..."
        fi
    fi

    docker compose -f "${COMPOSE_FILE}" up -d
    echo ""
    echo "  服务启动中，等待 15 秒..."
    sleep 15

    docker compose -f "${COMPOSE_FILE}" ps

    if [ "${DEPLOY_STACK}" = "pgsql" ] && [ -f "scripts/bootstrap-pgsql.sh" ]; then
        echo ""
        echo "  执行 PG 初始化（迁移 + 健康检查）..."
        COMPOSE_FILE="${COMPOSE_FILE}" bash scripts/bootstrap-pgsql.sh || echo "  [WARN] bootstrap 未完成，请手动运行 COMPOSE_FILE=${COMPOSE_FILE} bash scripts/bootstrap-pgsql.sh"
    fi

    if [ "${DEPLOY_STACK}" = "mysql" ] && [ -f "scripts/bootstrap-mysql.sh" ]; then
        echo ""
        echo "  执行 MySQL 初始化（迁移 + 健康检查）..."
        COMPOSE_FILE="${COMPOSE_FILE}" bash scripts/bootstrap-mysql.sh || echo "  [WARN] bootstrap 未完成，请手动运行 COMPOSE_FILE=${COMPOSE_FILE} bash scripts/bootstrap-mysql.sh"
    fi
else
    echo "  [ERROR] 未找到 ${COMPOSE_FILE}"
    exit 1
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
echo "   查看日志:  docker compose -f ${COMPOSE_FILE} logs -f"
echo "   停止:      docker compose -f ${COMPOSE_FILE} down"
echo "   重启:      docker compose -f ${COMPOSE_FILE} restart"
if [ "${DEPLOY_STACK}" = "pgsql" ]; then
echo "   PG 初始化: bash scripts/bootstrap-pgsql.sh"
elif [ "${DEPLOY_STACK}" = "mysql" ]; then
echo "   MySQL 初始化: bash scripts/bootstrap-mysql.sh"
fi
echo ""
echo " License 导入:"
echo "   bash scripts/import-license.sh /path/to/license.lic"
echo "=========================================="
