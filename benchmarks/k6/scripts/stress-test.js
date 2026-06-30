/**
 * M2-22 🛒 性能压测 — Stress Test（压力测试）
 *
 * 寻找系统极限：持续增加负载直到系统出现瓶颈或失败。
 * 目标：找出最大 QPS 和系统拐点
 *
 * 运行方式：
 *   k6 run benchmarks/k6/scripts/stress-test.js
 *
 * 环境变量：
 *   BASE_URL  — API 基础地址
 *   TOKEN     — 有效的 API Token
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://localhost:8000/api';
const token = __ENV.TOKEN;

const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

const errorRate = new Rate('errors');

export const options = {
    stages: [
        { duration: '2m', target: 50 },    // 热身
        { duration: '2m', target: 100 },   // 爬坡
        { duration: '2m', target: 200 },   // 更高
        { duration: '2m', target: 300 },   // 极限
        { duration: '1m', target: 500 },   // 冲刺
        { duration: '1m', target: 0 },     // 降温
    ],
    thresholds: {
        http_req_duration: ['p(99)<10000'],
        http_req_failed: ['rate<0.10'],
    },
};

export default function () {
    // 混合读取请求（80% 读 + 20% 写）
    const isRead = Math.random() < 0.8;

    if (isRead) {
        const endpoints = [
            '/admin/dashboard',
            '/admin/licenses?per_page=10',
            '/admin/customers?per_page=10',
            '/admin/products?per_page=10',
            '/health/live',
            '/health/ready',
        ];
        const path = endpoints[Math.floor(Math.random() * endpoints.length)];
        const res = http.get(`${baseUrl}${path}`, { headers });
        errorRate.add(res.status >= 400);
        check(res, { 'read status 2xx': (r) => r.status >= 200 && r.status < 300 });
    } else {
        const res = http.post(
            `${baseUrl}/license/validate`,
            JSON.stringify({ license_key: 'bench-stress-key', device_id: `device-${__VU}` }),
            { headers },
        );
        errorRate.add(res.status >= 400);
        check(res, { 'write status 2xx': (r) => r.status >= 200 && r.status < 300 });
    }

    sleep(0.1); // 短间隔模拟高并发
}
