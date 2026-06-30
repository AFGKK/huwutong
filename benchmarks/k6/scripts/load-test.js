/**
 * M2-22 🛒 性能压测 — Load Test（负载测试）
 *
 * 模拟正常峰值负载：逐步增加至 100 VUs，持续 3 分钟。
 * 目标：单机 ≥ 5000 QPS
 *
 * 运行方式：
 *   k6 run benchmarks/k6/scripts/load-test.js
 *
 * 环境变量：
 *   BASE_URL  — API 基础地址（默认 http://localhost:8000/api）
 *   TOKEN     — 有效的 API Token（必须提供）
 *   STAGE     — 测试阶段: smoke（快速）| full（完整）
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { randomIntBetween, randomString } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const baseUrl = __ENV.BASE_URL || 'http://localhost:8000/api';
const token = __ENV.TOKEN;
const stage = __ENV.STAGE || 'smoke';

const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

// ─── 自定义指标 ───
const licenseActivateTrend = new Trend('license_activate_duration');
const licenseValidateTrend = new Trend('license_validate_duration');
const orderCreateTrend = new Trend('order_create_duration');
const apiListTrend = new Trend('api_list_duration');
const totalOps = new Counter('total_operations');

// ─── 阈值配置 ───
export const thresholds = {
    http_req_duration: ['p(95)<2000', 'p(99)<5000'],
    http_req_failed: ['rate<0.05'],
    license_activate_duration: ['p(95)<3000'],
    license_validate_duration: ['p(95)<500'],
    order_create_duration: ['p(95)<3000'],
    api_list_duration: ['p(95)<1000'],
};

// ─── 负载配置 ───
const smokeStages = [
    { duration: '10s', target: 10 },   // 快速升温
    { duration: '20s', target: 10 },   // 稳定
    { duration: '10s', target: 0 },    // 降温
];

const fullStages = [
    { duration: '30s', target: 10 },   // 热身
    { duration: '30s', target: 30 },   // 爬坡
    { duration: '60s', target: 50 },   // 中等负载
    { duration: '60s', target: 100 },  // 峰值
    { duration: '30s', target: 100 },  // 持续峰值
    { duration: '30s', target: 0 },    // 降温
];

export const options = {
    stages: stage === 'full' ? fullStages : smokeStages,
    thresholds,
};

// ─── 预生成测试数据 ───
const testLicenses = Array.from({ length: 100 }, (_, i) => ({
    key: `BENCHMARK-HWT-${String(i).padStart(6, '0')}`,
    product_id: 1,
}));

const testCustomers = Array.from({ length: 50 }, (_, i) => ({
    id: i + 1,
    name: `Benchmark Customer ${i}`,
}));

// ─── 辅助函数 ───
function pickRandom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

// ─── 主场景 ───
export default function () {
    group('License 验证（高频）', function () {
        const license = pickRandom(testLicenses);
        const deviceId = `bench-device-${randomString(8)}`;

        // 激活
        const activatePayload = JSON.stringify({
            license_key: license.key,
            device_id: deviceId,
            fingerprint: `FP-${randomString(16)}`,
        });

        const activateRes = http.post(
            `${baseUrl}/license/activate`,
            activatePayload,
            { headers },
        );
        licenseActivateTrend.add(activateRes.timings.duration);
        check(activateRes, {
            'activate status 2xx': (r) => r.status >= 200 && r.status < 300,
        });
        totalOps.add(1);

        // 验证
        const validateRes = http.get(
            `${baseUrl}/license/validate?license_key=${license.key}&device_id=${deviceId}`,
            { headers },
        );
        licenseValidateTrend.add(validateRes.timings.duration);
        check(validateRes, {
            'validate status 2xx': (r) => r.status >= 200 && r.status < 300,
        });
        totalOps.add(1);

        sleep(randomIntBetween(0.1, 0.5));
    });

    group('管理后台列表（中频）', function () {
        const listEndpoints = [
            { path: '/admin/licenses?per_page=20', name: 'License' },
            { path: '/admin/customers?per_page=20', name: 'Customer' },
            { path: '/admin/orders?per_page=20', name: 'Order' },
            { path: '/admin/products?per_page=20', name: 'Product' },
            { path: '/admin/devices?per_page=20', name: 'Device' },
            { path: '/admin/subscriptions?per_page=20', name: 'Subscription' },
            { path: '/admin/invoices?per_page=20', name: 'Invoice' },
            { path: '/admin/tickets?per_page=20', name: 'Ticket' },
        ];

        const endpoint = pickRandom(listEndpoints);
        const res = http.get(`${baseUrl}${endpoint.path}`, { headers });
        apiListTrend.add(res.timings.duration);
        check(res, {
            [`${endpoint.name} list 2xx`]: (r) => r.status >= 200 && r.status < 300,
        });
        totalOps.add(1);

        sleep(randomIntBetween(0.2, 0.8));
    });

    group('订单创建（低频）', function () {
        const customer = pickRandom(testCustomers);
        const orderPayload = JSON.stringify({
            customer_id: customer.id,
            items: [
                {
                    sku_id: 1,
                    quantity: 1,
                    unit_price: 99.00,
                },
            ],
            total_amount: 99.00,
            currency: 'CNY',
        });

        const res = http.post(
            `${baseUrl}/admin/orders`,
            orderPayload,
            { headers },
        );
        orderCreateTrend.add(res.timings.duration);
        check(res, {
            'order create 2xx': (r) => r.status >= 200 && r.status < 300,
        });
        totalOps.add(1);

        sleep(randomIntBetween(0.5, 1.5));
    });

    // 心跳健康检查（高频）
    group('健康检查', function () {
        const res = http.get(`${baseUrl}/health/live`);
        check(res, {
            'health status 200': (r) => r.status === 200,
        });
        totalOps.add(1);
    });
}
