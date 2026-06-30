# 🏋️ M2-22 性能压测套件

> 单机 ≥ 5,000 QPS 验证

## 目录结构

```
benchmarks/
├── README.md              ← 本文件
├── k6/
│   ├── config.json         ← 全局配置
│   ├── scripts/
│   │   ├── smoke.js        ← 烟雾测试（快速验证）
│   │   ├── load-test.js    ← 负载测试（模拟峰值）
│   │   ├── stress-test.js  ← 压力测试（寻找极限）
│   │   └── spike-test.js   ← 突发测试（秒杀场景）
│   └── data/
│       └── test-data.json  ← 测试数据
├── results/               ← 测试结果（自动生成）
└── ...

docs/benchmarks/
└── index.md               ← 压测报告文档

app/Console/Commands/
└── BenchmarkCommand.php   ← 服务端基准测试 Artisan 命令
```

## 快速开始

```bash
# 1. 服务端基准测试（无需安装额外工具）
php artisan benchmark:run
php artisan benchmark:run --quick  # 快速模式

# 2. k6 端到端负载测试（需安装 k6）
#    安装: https://k6.io/docs/getting-started/installation/

export TOKEN="your-api-token"
k6 run benchmarks/k6/scripts/smoke.js
k6 run -e TOKEN=$TOKEN benchmarks/k6/scripts/load-test.js
```

## 测试矩阵

| 测试类型 | 并发 | 持续时间 | 目标 QPS | 适用场景 |
|:--------|:---:|:--------:|:--------:|:--------|
| Smoke   | 1   | 10s      | 可用性   | 开发/CI 快速验证 |
| Load    | 100 | 3m       | 5,000    | 正常业务峰值 |
| Stress  | 500 | 10m      | 10,000+  | 极限承载 |
| Spike   | 500 | 1.5m     | 突发     | 秒杀/促销 |

## 结果查看

- 服务端基准：`storage/app/benchmarks/report.json`
- k6 结果：终端直接输出 + `--summary-export=file.json`
- 压测报告：`docs/benchmarks/index.md`
