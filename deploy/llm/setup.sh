#!/bin/bash
# ===================================================
# 本地大模型部署脚本
# M3-49 LocalLLMDeploy
#
# 使用方式:
#   bash deploy/llm/setup.sh ollama    # 部署 Ollama
#   bash deploy/llm/setup.sh vllm      # 部署 vLLM
#   bash deploy/llm/setup.sh models    # 下载推荐模型
# ===================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
INFRA_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

info() { echo -e "${GREEN}[INFO]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

show_usage() {
    echo "使用方式: $0 {ollama|vllm|models|status}"
    echo ""
    echo "命令:"
    echo "  ollama    部署 Ollama (CPU/GPU)"
    echo "  vllm      部署 vLLM (GPU 推荐)"
    echo "  models    下载推荐模型"
    echo "  status    查看运行状态"
    exit 1
}

CMD="${1:-}"
[ -z "${CMD}" ] && show_usage

case "${CMD}" in
    ollama)
        info "部署 Ollama..."
        cd "${INFRA_DIR}"
        docker compose -f deploy/llm/docker-compose.ollama.yml up -d
        info "等待启动..."
        sleep 5
        curl -s http://localhost:11434/api/tags && info "✅ Ollama 已启动" || error "Ollama 启动失败"
        ;;

    vllm)
        info "部署 vLLM..."
        cd "${INFRA_DIR}"
        docker compose -f deploy/llm/docker-compose.vllm.yml up -d
        info "vLLM 启动可能需要 2-5 分钟加载模型..."
        sleep 10
        curl -s http://localhost:8000/v1/models && info "✅ vLLM 已启动" || error "vLLM 启动中，请稍后检查"
        ;;

    models)
        info "下载推荐模型到 Ollama..."
        MODELS=("qwen2:7b" "nomic-embed-text" "deepseek-r1:7b")
        for model in "${MODELS[@]}"; do
            info "下载模型: ${model}"
            docker exec hwt-ollama ollama pull "${model}" || error "下载 ${model} 失败"
        done
        info "✅ 模型下载完成"
        docker exec hwt-ollama ollama list
        ;;

    status)
        info "检查 LLM 服务状态..."
        echo ""
        echo "--- Ollama ---"
        curl -sf http://localhost:11434/api/tags > /dev/null 2>&1 \
            && echo -e "  状态: ${GREEN}运行中${NC}" \
            || echo -e "  状态: ${RED}未运行${NC}"
        if command -v docker &>/dev/null; then
            docker exec hwt-ollama ollama list 2>/dev/null || echo "  容器未运行"
        fi

        echo ""
        echo "--- vLLM ---"
        curl -sf http://localhost:8000/v1/models > /dev/null 2>&1 \
            && echo -e "  状态: ${GREEN}运行中${NC}" \
            || echo -e "  状态: ${RED}未运行${NC}"

        echo ""
        echo "--- GPU ---"
        if command -v nvidia-smi &>/dev/null; then
            nvidia-smi --query-gpu=name,memory.used,memory.total,utilization.gpu --format=csv,noheader 2>/dev/null || echo "  GPU 不可用"
        else
            echo "  未检测到 nvidia-smi"
        fi
        ;;

    *)
        show_usage
        ;;
esac
