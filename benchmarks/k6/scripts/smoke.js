/**
 * M2-22 🛒 性能压测 — Smoke Test（烟雾测试）
 *
 * 最小负载验证：1 VUs × 10s，确认 API 基本可用。
 *
 * 运行方式：
 *   k6 run benchmarks/k6/scripts/smoke.js
 *
 * 环境变量：
 *   BASE_URL  — API 基础地址（默认 http://localhost:8000/api）
 *   TOKEN     — 有效的 API Token（必须提供）
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://localhost:8000/api';
const token = __ENV.TOKEN;

const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

// 自定义指标
const smokeFailRate = new Rate('smoke_failed_checks');

export const options = {
    vus: 1,
    duration: '10s',
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% 请求应在 500ms 内
        http_req_failed: ['rate<0.01'],    // 错误率 < 1%
    },
};

const endpoints = [
    { name: 'Dashboard', method: 'GET', path: '/admin/dashboard' },
    { name: 'License List', method: 'GET', path: '/admin/licenses?per_page=10' },
    { name: 'Customer List', method: 'GET', path: '/admin/customers?per_page=10' },
    { name: 'Product List', method: 'GET', path: '/admin/products?per_page=10' },
    { name: 'Order List', method: 'GET', path: '/admin/orders?per_page=10' },
    { name: 'Health Check', method: 'GET', path: '/health/live' },
];

export default function () {
    endpoints.forEach(({ name, method, path }) => {
        const url = method === 'GET'
            ? `${baseUrl}${path}`
            : `${baseUrl}${path}`;

        const res = http.request(method, url, null, { headers });

        const ok = check(res, {
            [`${name} status 2xx`]: (r) => r.status >= 200 && r.status < 300,
            [`${name} duration < 500ms`]: (r) => r.timings.duration < 500,
        });

        smokeFailRate.add(!ok);
    });

    sleep(1);
}
