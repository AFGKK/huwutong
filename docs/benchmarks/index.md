# M2-22 🛒 性能压测报告

> **任务**：M2-22 性能压测（单机≥5000 QPS 验证）
> **日期**：2026-06-14
> **版本**：v1.0

---

## 1. 测试目标

| 指标 | 目标值 | 说明 |
|:----|:-----:|:----|
| **综合 QPS** | ≥ 5,000 | 单机每秒处理请求数 |
| **License 验证 P95** | < 500ms | 最频繁调用的 API |
| **License 激活 P95** | < 3,000ms | 含设备绑定和签名 |
| **列表查询 P95** | < 1,000ms | 分页查询 |
| **错误率** | < 5% | 失败请求占比 |

## 2. 测试工具

| 工具 | 用途 | 位置 |
|:----|:----|:----|
| **k6** | 端到端负载测试（推荐） | `benchmarks/k6/scripts/` |
| **Artisan 命令** | 服务端基准测试 | `php artisan benchmark:run` |

## 3. 测试场景

### 3.1 烟雾测试 (Smoke)
- 1 VUs × 10s
- 验证所有核心 API 端点基本可用
- 命令：`k6 run benchmarks/k6/scripts/smoke.js`

### 3.2 负载测试 (Load)
- 逐步增加到 100 VUs，持续 3 分钟
- 模拟正常业务峰值
- 命令：`k6 run benchmarks/k6/scripts/load-test.js`

### 3.3 压力测试 (Stress)
- 逐步增加到 500 VUs，持续 10 分钟
- 寻找系统拐点
- 命令：`k6 run benchmarks/k6/scripts/stress-test.js`

### 3.4 突发流量测试 (Spike)
- 10 秒内激增到 500 VUs
- 模拟秒杀/促销场景
- 命令：`k6 run benchmarks/k6/scripts/spike-test.js`

### 3.5 服务端基准测试
- PHP 端直接执行，评估框架/数据库性能
- 命令：`php artisan benchmark:run`

## 4. 运行方式

### 前置条件
```bash
# 0. 启动 D-39 压测栈（推荐，非 artisan serve）
powershell -File scripts/benchmark-up.ps1   # Windows
# export BASE_URL=http://127.0.0.1:8088/api

# 1. 安装 k6（macOS）
brew install k6

# 2. 安装 k6（Windows）
winget install k6

# 3. 获取 API Token（管理后台 → API 密钥）
export TOKEN="your-api-token-here"
```

### 执行测试
```bash
# 快速烟雾测试
k6 run benchmarks/k6/scripts/smoke.js

# 完整负载测试
k6 run -e TOKEN=$TOKEN benchmarks/k6/scripts/load-test.js

# 压力测试
k6 run -e TOKEN=$TOKEN benchmarks/k6/scripts/stress-test.js

# 突发流量测试
k6 run -e TOKEN=$TOKEN benchmarks/k6/scripts/spike-test.js

# 服务端基准测试（无需 k6）
php artisan benchmark:run
```

### D-40 达标验证（归档）
```bash
# 完整流程（优先 D-39 栈 8088，回退 artisan serve 8000）
powershell -ExecutionPolicy Bypass -File scripts/benchmark-run-full.ps1

# 仅生成 PHP 层报告
php artisan benchmark:report --base-url=http://127.0.0.1:8088/api --target-qps=5000 --try-k6

# k6 恒定到达率（需安装 k6 + D-39 栈）
k6 run -e BASE_URL=http://127.0.0.1:8088/api -e TARGET_QPS=5000 \
  --summary-export=benchmarks/results/k6-qps-summary.json \
  benchmarks/k6/scripts/qps-target.js

# k6 混合负载 D-40 阶段
k6 run -e BASE_URL=http://127.0.0.1:8088/api -e TOKEN=$TOKEN -e STAGE=d40 \
  benchmarks/k6/scripts/load-test.js
```

归档报告路径：`benchmarks/results/benchmark-result.json`


## 5. 测试环境

| 项目 | 配置 |
|:----|:----|
| **服务器** | 开发机 / CI Runner |
| **PHP** | 8.2+ |
| **数据库** | MySQL 8.0 |
| **缓存** | Redis 7.0 |
| **Web 服务器** | Laravel Artisan / Nginx + FPM |

## 6. 结果解读

### k6 输出示例
```
http_req_duration..............: avg=12.3ms  p(95)=45.2ms
http_req_failed................: 0.02%
✓ license_validate_duration....: avg=8.1ms   p(95)=22.5ms
✓ license_activate_duration....: avg=156ms   p(95)=412ms
✓ api_list_duration............: avg=35.4ms  p(95)=89.2ms
```

### Artisan 输出示例
```
 测试项                          │ 迭代次数 │ 总耗时(ms) │ 平均(ms) │ QPS     │ 内存(MB)
 DB 读取 (SELECT 20条)           │ 1,000    │ 1,234      │ 1.234    │ 810.4   │ 4.25
 License 验证（含激活记录关联）   │ 1,000    │ 892        │ 0.892    │ 1,121.1 │ 3.87
```

## 7. 优化建议

- 启用 **Opcache** 和 **JIT** 提升 PHP 执行性能
- 配置 **MySQL 查询缓存** 或 **Redis 结果缓存**
- 高频验证接口使用 **Redis 缓存** 代替数据库查询
- 启用 **Nginx FastCGI Cache** 或 **Laravel Octane**
- 数据库添加 **复合索引**：`(license_key, status)`、`(tenant_id, status)`

## 8. 历史记录

| 日期 | 版本 | 综合 QPS | 结果 | 备注 |
|:----|:---:|:--------:|:----:|:----|
| 2026-06-14 | v1.0 | - | ⏳ 待执行 | 初始基准 |
| 2026-07-12 | v1.1 | **0.4** (HTTP) | ❌ 未达标 | `artisan serve` :8000；100 req×并发1；P95 2778ms；k6 未安装 |
| 2026-07-12 | v1.1 | ~1190 (服务端) | — | `benchmark:run` License 验证项；应用层 DB 性能基线 |
| — | 目标 | **≥5000** | — | 需 D-39 Nginx+PHP-FPM 栈 (8088) + k6 重测 |
