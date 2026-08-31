/**
 * T-24: 5000 QPS 达标验证 — 恒定到达率压测（Octane 优化版）
 *
 * 使用 constant-arrival-rate 向 /health/live 施加目标 QPS。
 * 特别适配 Octane (Swoole) 高吞吐，使用 HTTP/1.1 keepalive 减少连接开销。
 *
 * 运行:
 *   # Octane Swoole 模式（端口 8000 直连，绕过 Nginx）
 *   k6 run -e TARGET_URL=http://app-octane:8000 -e TARGET_QPS=5000 benchmarks/k6/scripts/qps-target.js
 *
 *   # 通过 Nginx 代理（PHP-FPM 或 Octane）
 *   k6 run -e BASE_URL=http://nginx/api -e TARGET_QPS=5000 benchmarks/k6/scripts/qps-target.js
 *
 *   k6 run --summary-export=benchmarks/results/k6-qps-summary.json ...
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// 连接池配置：Octane 支持 keepalive，减少 TCP 握手开销
const baseUrl = __ENV.BASE_URL || __ENV.TARGET_URL || 'http://127.0.0.1:8088/api';
const targetQps = parseInt(__ENV.TARGET_QPS || '5000', 10);
const duration = __ENV.DURATION || '2m';
const healthPath = __ENV.HEALTH_PATH || '/health/live';

// 预热 QPS（前 10 秒逐渐上升）
const warmupQps = Math.min(targetQps * 0.3, 1500);

const healthDuration = new Trend('health_duration');
const healthErrors = new Rate('health_errors');
const totalRequests = new Counter('health_requests');

export const options = {
    scenarios: {
        warmup: {
            executor: 'ramping-arrival-rate',
            startRate: warmupQps,
            timeUnit: '1s',
            preAllocatedVUs: Math.min(Math.ceil(targetQps / 10), 500),
            maxVUs: Math.min(targetQps, 1000),
            stages: [
                { duration: '10s', target: targetQps },
            ],
            exec: 'healthCheck',
        },
        qps_target: {
            executor: 'constant-arrival-rate',
            rate: targetQps,
            timeUnit: '1s',
            duration: duration,
            preAllocatedVUs: Math.min(Math.ceil(targetQps / 10), 500),
            maxVUs: Math.min(targetQps, 1000),
            startTime: '10s',
            exec: 'healthCheck',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],                      // 错误率 < 1%
        http_reqs: [`rate>=${targetQps * 0.95}`],            // 实际 QPS >= 95% 目标
        health_duration: [`p(95)<500`],                       // 95% 请求 < 500ms
        health_errors: ['rate<0.01'],
    },
};

export function healthCheck() {
    // 使用 tags 区分 warmup 和 main 阶段
    const res = http.get(`${baseUrl}${healthPath}`, {
        tags: { name: 'health_live' },
        timeout: '10s',
    });

    healthDuration.add(res.timings.duration);
    healthErrors.add(res.status !== 200);
    totalRequests.add(1);

    check(res, {
        'health 200': (r) => r.status === 200,
        'health fast': (r) => r.timings.duration < 500,
    });
}

export function handleSummary(data) {
    const rate = data.metrics?.http_reqs?.values?.rate ?? 0;
    const p95 = data.metrics?.health_duration?.values?.['p(95)']
                ?? data.metrics?.http_req_duration?.values?.['p(95)']
                ?? 0;
    const failed = data.metrics?.http_req_failed?.values?.rate ?? 0;
    const median = data.metrics?.health_duration?.values?.median
                   ?? data.metrics?.http_req_duration?.values?.median
                   ?? 0;
    const p99 = data.metrics?.health_duration?.values?.['p(99)']
                ?? data.metrics?.http_req_duration?.values?.['p(99)']
                ?? 0;

    // 目标达成检查
    const achievedQps = Math.round(rate * 10) / 10;
    const passed = rate >= targetQps * 0.95;

    const summary = {
        mission: 'T-24',
        test: 'qps-target',
        target_qps: targetQps,
        achieved_qps: achievedQps,
        passed: passed,
        p95_ms: Math.round(p95 * 10) / 10,
        p99_ms: Math.round(p99 * 10) / 10,
        median_ms: Math.round(median * 10) / 10,
        error_rate: Math.round(failed * 10000) / 10000,
        base_url: baseUrl,
        duration: duration,
        timestamp: new Date().toISOString(),
    };

    // 如果是在 Docker 容器内，写入共享卷
    const output = {};
    try {
        output['/results/k6-qps-summary.json'] = JSON.stringify(summary, null, 2);
    } catch (e) {
        // 非容器环境，跳过文件写入
        output['benchmarks/results/k6-qps-summary.json'] = JSON.stringify(summary, null, 2);
    }

    // 控制台输出
    output.stdout = textSummary(data, summary);

    return output;
}

function textSummary(data, summary) {
    return [
        '',
        '=== T-24 QPS Target Summary ===',
        `  Target QPS:    ${summary.target_qps}`,
        `  Achieved QPS:  ${summary.achieved_qps}`,
        `  Passed:        ${summary.passed ? 'YES' : 'NO'}`,
        `  Median:        ${summary.median_ms} ms`,
        `  P95:           ${summary.p95_ms} ms`,
        `  P99:           ${summary.p99_ms} ms`,
        `  Error Rate:    ${(summary.error_rate * 100).toFixed(2)}%`,
        `  Base URL:      ${summary.base_url}`,
        '',
    ].join('\n');
}

export default healthCheck;
