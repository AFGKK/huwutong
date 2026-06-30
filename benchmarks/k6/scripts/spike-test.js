/**
 * M2-22 🛒 性能压测 — Spike Test（突发流量测试）
 *
 * 模拟突发峰值流量（如秒杀场景）：瞬间激增到极高负载后回落。
 *
 * 运行方式：
 *   k6 run benchmarks/k6/scripts/spike-test.js
 *
 * 环境变量：
 *   BASE_URL  — API 基础地址
 *   TOKEN     — 有效的 API Token
 */

import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = __ENV.BASE_URL || 'http://localhost:8000/api';
const token = __ENV.TOKEN;

const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

export const options = {
    stages: [
        { duration: '30s', target: 10 },    // 平稳
        { duration: '10s', target: 500 },   // 瞬间激增
        { duration: '30s', target: 500 },   // 承受峰值
        { duration: '30s', target: 10 },    // 快速回落
        { duration: '30s', target: 0 },     // 恢复
    ],
    thresholds: {
        http_req_duration: ['p(95)<5000'],
        http_req_failed: ['rate<0.05'],
    },
};

export default function () {
    const res = http.get(`${baseUrl}/license/validate?license_key=bench-spike-key&device_id=spike-${__VU}`, { headers });
    check(res, {
        'validate status 2xx': (r) => r.status >= 200 && r.status < 300,
        'validate duration < 2000ms': (r) => r.timings.duration < 2000,
    });
    sleep(0.5);
}
