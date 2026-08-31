# T-24: 启动 benchmark 压测环境（Octane Swoole 模式）
# 用法: ./scripts/benchmark-octane.sh

echo "=== HWT Benchmark: Octane Swoole Mode ==="

docker compose -f deploy/benchmark/docker-compose.benchmark.yml \
  --profile octane up -d --build

echo ""
echo "等待服务启动..."
sleep 10

echo "测试健康端点:"
curl -sf http://127.0.0.1:8088/api/health/live && echo " OK"

echo ""
echo "运行 k6 QPS 压测:"
docker compose -f deploy/benchmark/docker-compose.benchmark.yml \
  --profile k6 run --rm k6-qps

echo ""
echo "=== Done ==="
