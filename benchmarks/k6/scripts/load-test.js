/**
 * M2-22 / D-40 性能压测 — Load Test（混合 API 负载）
 *
 * STAGE=smoke | full | d40
 * D-40 配合 qps-target.js 使用（健康端点 5000 QPS + 本脚本混合 API）
 *
 *   k6 run -e BASE_URL=http://127.0.0.1:8088/api -e TOKEN=xxx -e STAGE=d40 benchmarks/k6/scripts/load-test.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend, Counter } from 'k6/metrics';
import { randomIntBetween, randomString } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const baseUrl = __ENV.BASE_URL || 'http://127.0.0.1:8088/api';
const token = __ENV.TOKEN || '';
const stage = __ENV.STAGE || 'smoke';

const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

const licenseActivateTrend = new Trend('license_activate_duration');
const licenseValidateTrend = new Trend('license_validate_duration');
const orderCreateTrend = new Trend('order_create_duration');
const apiListTrend = new Trend('api_list_duration');
const totalOps = new Counter('total_operations');

export const thresholds = {
    http_req_duration: ['p(95)<2000', 'p(99)<5000'],
    http_req_failed: ['rate<0.05'],
    license_activate_duration: ['p(95)<3000'],
    license_validate_duration: ['p(95)<500'],
    order_create_duration: ['p(95)<3000'],
    api_list_duration: ['p(95)<1000'],
};

const smokeStages = [
    { duration: '10s', target: 10 },
    { duration: '20s', target: 10 },
    { duration: '10s', target: 0 },
];

const fullStages = [
    { duration: '30s', target: 20 },
    { duration: '30s', target: 50 },
    { duration: '60s', target: 100 },
    { duration: '90s', target: 200 },
    { duration: '60s', target: 200 },
    { duration: '30s', target: 0 },
];

const d40Stages = [
    { duration: '20s', target: 30 },
    { duration: '40s', target: 80 },
    { duration: '60s', target: 150 },
    { duration: '90s', target: 250 },
    { duration: '60s', target: 250 },
    { duration: '30s', target: 0 },
];

export const options = {
    stages: stage === 'd40' ? d40Stages : (stage === 'full' ? fullStages : smokeStages),
    thresholds,
};

const testLicenses = Array.from({ length: 100 }, (_, i) => ({
    key: `BENCHMARK-HWT-${String(i).padStart(6, '0')}`,
}));

const testCustomers = Array.from({ length: 50 }, (_, i) => ({ id: i + 1 }));

function pickRandom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

export default function () {
    // D-40 模式：健康检查权重更高
    if (stage === 'd40' || Math.random() < 0.4) {
        group('健康检查', function () {
            const res = http.get(`${baseUrl}/health/live`);
            check(res, { 'health 200': (r) => r.status === 200 });
            totalOps.add(1);
        });
        sleep(randomIntBetween(0.01, 0.05));
    }

    if (token && Math.random() < 0.5) {
        group('License 验证', function () {
            const license = pickRandom(testLicenses);
            const deviceId = `bench-${randomString(8)}`;
            const validateRes = http.get(
                `${baseUrl}/license/validate?license_key=${license.key}&device_id=${deviceId}`,
                { headers },
            );
            licenseValidateTrend.add(validateRes.timings.duration);
            check(validateRes, { 'validate 2xx': (r) => r.status >= 200 && r.status < 300 });
            totalOps.add(1);
        });
        sleep(randomIntBetween(0.05, 0.15));
    }

    if (token && stage !== 'smoke') {
        group('管理后台列表', function () {
            const paths = [
                '/admin/licenses?per_page=20',
                '/admin/customers?per_page=20',
                '/admin/products?per_page=20',
            ];
            const res = http.get(`${baseUrl}${pickRandom(paths)}`, { headers });
            apiListTrend.add(res.timings.duration);
            check(res, { 'list 2xx': (r) => r.status >= 200 && r.status < 300 });
            totalOps.add(1);
        });
        sleep(randomIntBetween(0.1, 0.3));
    }
}
