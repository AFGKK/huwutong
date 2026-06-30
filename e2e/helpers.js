/**
 * E2E 测试共用辅助函数
 *
 * 提供模拟登录、API 拦截等功能，
 * 使 E2E 测试无需依赖真实后端认证即可测试前端页面。
 */

/**
 * 生成模拟用户数据
 */
export function getMockUser(email = 'admin@huwutong.com', name = '超级管理员') {
    return {
        id: 1,
        name,
        email,
        tenant_id: 1,
        active_tenant_id: 1,
        tenants: [
            { id: 1, name: '互物通科技', slug: 'huwutong', logo: null },
        ],
        is_multi_tenant: false,
        roles: ['super-admin', 'admin'],
        email_verified_at: new Date().toISOString(),
        status: 'active',
        has_password: true,
        has_phone: false,
    };
}

/**
 * 生成模拟 License 数据
 */
export function getMockLicenses(count = 5) {
    const statuses = ['active', 'expired', 'suspended', 'pending'];
    const plans = ['enterprise', 'professional', 'starter', 'trial'];
    const now = Date.now();

    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        license_key: `HWT-${String(100000 + i).slice(1)}-${String(200000 + i * 2).slice(1)}-${String(300000 + i * 3).slice(1)}-${String(400000 + i * 4).slice(1)}`,
        product: { id: 1, name: `产品 ${i + 1}` },
        customer: { id: 1, name: `客户 ${i + 1}` },
        status: statuses[i % statuses.length],
        plan: plans[i % plans.length],
        seats: 5 + i * 5,
        activated_at: now - 86400000 * (30 + i * 10),
        expires_at: now + 86400000 * (30 + i * 30),
        created_at: now - 86400000 * (60 + i * 10),
        updated_at: now - 86400000 * i,
    }));
}

/**
 * 生成模拟单个 License 详情
 */
export function getMockLicenseDetail(id = 1) {
    const now = Date.now();
    return {
        id,
        license_key: 'HWT-12345-67890-ABCDE-FGHIJ',
        product: { id: 1, name: '企业版 License', version: '3.2.1' },
        customer: { id: 1, name: '互物通科技', email: 'contact@huwutong.com' },
        status: 'active',
        plan: 'enterprise',
        seats: 50,
        used_seats: 23,
        activated_at: now - 86400000 * 90,
        expires_at: now + 86400000 * 275,
        created_at: now - 86400000 * 100,
        updated_at: now - 86400000 * 2,
        features: [
            { code: 'multi_tenant', name: '多租户支持', enabled: true },
            { code: 'audit_log', name: '审计日志', enabled: true },
            { code: 'sso', name: '单点登录', enabled: false },
            { code: 'api_access', name: 'API 访问', enabled: true },
        ],
        devices: [
            { id: 1, name: '生产服务器-01', ip: '192.168.1.100', last_seen: now - 3600000 },
            { id: 2, name: '生产服务器-02', ip: '192.168.1.101', last_seen: now - 7200000 },
        ],
        activations: [
            { id: 1, device_name: '生产服务器-01', activated_at: now - 86400000 * 80, status: 'active' },
            { id: 2, device_name: '生产服务器-02', activated_at: now - 86400000 * 60, status: 'active' },
        ],
    };
}

/**
 * 生成模拟产品列表
 */
export function getMockProducts(count = 3) {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        name: `产品 ${i + 1}`,
        slug: `product-${i + 1}`,
        description: `这是产品 ${i + 1} 的描述`,
        version: `1.${i}.0`,
        status: 'active',
        price: (i + 1) * 1000,
        created_at: new Date().toISOString(),
    }));
}

/**
 * 生成模拟客户列表
 */
export function getMockCustomers(count = 4) {
    return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        name: `客户 ${i + 1}`,
        email: `customer${i + 1}@example.com`,
        company: `公司 ${i + 1}`,
        status: 'active',
        licenses_count: i + 1,
        created_at: new Date().toISOString(),
    }));
}

/**
 * 模拟分页响应包装
 */
function paginated(data, total) {
    return {
        success: true,
        data,
        meta: { current_page: 1, last_page: 1, per_page: 15, total: total || data.length },
        message: 'ok',
    };
}

/**
 * 获取模拟角色列表
 */
export function getMockRoles() {
    return [
        { id: 1, name: '超级管理员', guard_name: 'web', permissions: ['*'] },
        { id: 2, name: '管理员', guard_name: 'web', permissions: ['license.*', 'customer.*'] },
        { id: 3, name: '操作员', guard_name: 'web', permissions: ['license.view'] },
    ];
}

/**
 * 获取模拟 API 密钥列表
 */
export function getMockApiKeys() {
    return [
        { id: 1, name: '生产环境密钥', key: 'hwt_prod_********abcd', last_used_at: new Date().toISOString(), created_at: new Date().toISOString(), expires_at: null },
        { id: 2, name: '测试密钥', key: 'hwt_test_********efgh', last_used_at: null, created_at: new Date().toISOString(), expires_at: new Date(Date.now() + 86400000 * 30).toISOString() },
    ];
}

/**
 * 获取模拟诊断记录
 */
export function getMockDiagnostics() {
    return Array.from({ length: 5 }, (_, i) => ({
        id: i + 1,
        error_code: `ERR-${1000 + i}`,
        title: `错误码 ${1000 + i} 诊断结果`,
        severity: ['critical', 'high', 'medium', 'low', 'info'][i],
        summary: `这是一个模拟的诊断结果 #${i + 1}`,
        created_at: new Date(Date.now() - i * 3600000).toISOString(),
    }));
}

/**
 * 获取模拟 RAG 文档列表
 */
export function getMockRagDocuments() {
    return Array.from({ length: 4 }, (_, i) => ({
        id: i + 1,
        title: `知识库文档 ${i + 1}`,
        status: ['indexed', 'indexed', 'pending', 'failed'][i],
        chunk_count: Math.floor(Math.random() * 50) + 10,
        created_at: new Date(Date.now() - i * 86400000).toISOString(),
    }));
}

/**
 * 获取模拟 LLM Provider 列表
 */
export function getMockLlmProviders() {
    return [
        { id: 1, name: 'OpenAI', slug: 'openai', driver: 'openai', models: ['gpt-4o', 'gpt-3.5-turbo'], default_model: 'gpt-4o', is_active: true, sort_order: 1 },
        { id: 2, name: 'Azure OpenAI', slug: 'azure', driver: 'azure', models: ['gpt-4o'], default_model: 'gpt-4o', is_active: true, sort_order: 2 },
        { id: 3, name: '通义千问', slug: 'qwen', driver: 'openai', models: ['qwen-max', 'qwen-plus'], default_model: 'qwen-plus', is_active: false, sort_order: 3 },
    ];
}

/**
 * 获取模拟工单列表
 */
export function getMockTickets() {
    return Array.from({ length: 5 }, (_, i) => ({
        id: i + 1,
        title: `工单 #${1000 + i}: ${['技术支持', '账户问题', '功能请求', '账单问题', '其他'][i]}`,
        status: ['open', 'pending', 'replied', 'resolved', 'closed'][i],
        priority: ['high', 'medium', 'low', 'medium', 'low'][i],
        customer: { id: 1, name: '测试客户' },
        created_at: new Date(Date.now() - i * 86400000).toISOString(),
    }));
}

/**
 * 获取模拟更新包列表
 */
export function getMockUpdatePackages() {
    return Array.from({ length: 3 }, (_, i) => ({
        id: i + 1,
        product_id: 1,
        version: `2.${i}.0`,
        type: ['full', 'incremental', 'hotfix'][i],
        status: ['published', 'draft', 'deprecated'][i],
        file_size: 1024 * 1024 * (i + 1) * 10,
        created_at: new Date(Date.now() - i * 86400000 * 7).toISOString(),
    }));
}

/**
 * 获取模拟域名列表
 */
export function getMockDomains() {
    return [
        { id: 1, domain: 'app.example.com', status: 'verified', verified_at: new Date().toISOString(), ssl_status: 'active' },
        { id: 2, domain: 'test.example.com', status: 'pending', verified_at: null, ssl_status: 'none' },
    ];
}

/**
 * 获取模拟漏洞列表
 */
export function getMockVulnerabilities() {
    return Array.from({ length: 4 }, (_, i) => ({
        id: i + 1,
        package_name: ['laravel/framework', 'guzzlehttp/guzzle', 'npm/axios', 'lodash'][i],
        ecosystem: ['composer', 'composer', 'npm', 'npm'][i],
        severity: ['critical', 'high', 'medium', 'low'][i],
        status: ['open', 'open', 'fixed', 'ignored'][i],
        cve: `CVE-2026-${1000 + i}`,
        title: `模拟安全漏洞 #${i + 1}`,
        detected_at: new Date(Date.now() - i * 86400000).toISOString(),
    }));
}

/**
 * 获取模拟功能开关列表
 */
export function getMockFeatureFlags() {
    return [
        { id: 1, key: 'new_dashboard', name: '新版仪表盘', description: '启用新版仪表盘 UI', enabled: true, tenant_overridable: true },
        { id: 2, key: 'ai_chat', name: 'AI 智能客服', description: '启用 AI 客服功能', enabled: true, tenant_overridable: false },
        { id: 3, key: 'advanced_reporting', name: '高级报表', description: '启用高级报表功能', enabled: false, tenant_overridable: true },
    ];
}

/**
 * 获取模拟税务列表
 */
export function getMockTaxRates() {
    return [
        { id: 1, country_code: 'CN', region_code: null, name: '中国增值税', rate: 0.13, type: 'vat', is_active: true },
        { id: 2, country_code: 'US', region_code: 'CA', name: 'California Sales Tax', rate: 0.0875, type: 'sales_tax', is_active: true },
        { id: 3, country_code: 'DE', region_code: null, name: 'Germany VAT', rate: 0.19, type: 'vat', is_active: true },
    ];
}

/**
 * 获取模拟沙箱信息
 */
export function getMockSandboxInfo() {
    return {
        id: 1,
        name: '我的沙箱',
        status: 'active',
        created_at: new Date(Date.now() - 86400000 * 30).toISOString(),
        licenses_count: 5,
        devices_count: 3,
    };
}

/**
 * 获取模拟 Staging 环境信息
 */
export function getMockStagingInfo() {
    return {
        id: 1,
        name: '集成测试环境',
        status: 'active',
        subdomain: 'staging-1.huwutong.com',
        rate_limit: 60,
        created_at: new Date(Date.now() - 86400000 * 15).toISOString(),
        licenses_count: 10,
    };
}

/**
 * 获取模拟审计日志列表
 */
export function getMockAuditLogs() {
    return Array.from({ length: 8 }, (_, i) => ({
        id: i + 1,
        user: 'admin@huwutong.com',
        action: ['login', 'create_license', 'update_license', 'delete_license'][i % 4],
        target: `License #${i + 1}`,
        ip: '127.0.0.1',
        created_at: new Date(Date.now() - i * 3600000).toISOString(),
    }));
}

/**
 * 在页面上设置 API 拦截 mock
 * 拦截所有 /api/ 请求并返回模拟数据
 */
export async function setupApiMocks(page, options = {}) {
    const {
        mockUser = getMockUser(),
        mockLicenses = getMockLicenses(),
        mockLicenseDetail = getMockLicenseDetail(),
        mockProducts = getMockProducts(),
        mockCustomers = getMockCustomers(),
        mockRoles = getMockRoles(),
        mockApiKeys = getMockApiKeys(),
        mockDiagnostics = getMockDiagnostics(),
        mockRagDocuments = getMockRagDocuments(),
        mockLlmProviders = getMockLlmProviders(),
        mockTickets = getMockTickets(),
        mockUpdatePackages = getMockUpdatePackages(),
        mockDomains = getMockDomains(),
        mockVulnerabilities = getMockVulnerabilities(),
        mockFeatureFlags = getMockFeatureFlags(),
        mockTaxRates = getMockTaxRates(),
        mockSandboxInfo = getMockSandboxInfo(),
        mockStagingInfo = getMockStagingInfo(),
        mockAuditLogs = getMockAuditLogs(),
    } = options;

    await page.route('**/api/**', async (route) => {
        const url = route.request().url();
        const method = route.request().method();

        // ── 认证相关 ──
        if (url.includes('/api/user') && method === 'GET') {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true, data: mockUser, message: 'ok' }),
            });
        }
        if (url.includes('/api/logout')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true, message: 'ok' }),
            });
        }
        if (url.includes('/api/login')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { user: mockUser, token: 'e2e-test-token' },
                    message: '登录成功',
                }),
            });
        }
        if (url.includes('/api/register')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { user: mockUser, token: 'e2e-test-token' },
                    message: '注册成功',
                }),
            });
        }
        if (url.includes('/api/switch-tenant')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true, data: {}, message: 'ok' }),
            });
        }

        // ── License ──
        if (url.includes('/api/licenses/stats')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: { total: 5, active: 3, expired: 1, expiring_soon: 1, by_status: { active: 3, expired: 1, pending: 1 }, by_type: {} },
                    message: 'ok',
                }),
            });
        }
        if (url.includes('/api/licenses') && method === 'GET') {
            // 是列表还是详情？
            const match = url.match(/\/api\/licenses\/(\d+)/);
            if (match) {
                // 详情
                return await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        success: true,
                        data: { ...mockLicenseDetail, id: parseInt(match[1]) },
                        message: 'ok',
                    }),
                });
            }
            // 列表（带分页）
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockLicenses)),
            });
        }

        // ── 产品 ──
        if (url.includes('/api/products')) {
            const match = url.match(/\/api\/products\/(\d+)/);
            if (match) {
                const product = mockProducts.find(p => p.id === parseInt(match[1])) || mockProducts[0];
                return await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: product, message: 'ok' }),
                });
            }
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockProducts)),
            });
        }

        // ── 客户 ──
        if (url.includes('/api/customers')) {
            const match = url.match(/\/api\/customers\/(\d+)/);
            if (match) {
                const customer = mockCustomers.find(c => c.id === parseInt(match[1])) || mockCustomers[0];
                return await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, data: { ...customer, licenses: [] }, message: 'ok' }),
                });
            }
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockCustomers)),
            });
        }

        // ── 设置 ──
        if (url.includes('/api/settings')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        site_name: 'HWT License',
                        site_logo: '',
                        timezone: 'Asia/Shanghai',
                        language: 'zh-CN',
                        maintenance_mode: false,
                    },
                    message: 'ok',
                }),
            });
        }

        // ── 系统健康 ──
        if (url.includes('/api/health')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        status: 'healthy',
                        services: [
                            { name: 'Database', status: 'healthy', latency_ms: 3 },
                            { name: 'Redis', status: 'healthy', latency_ms: 1 },
                            { name: 'Queue', status: 'healthy', latency_ms: 5 },
                        ],
                        uptime_seconds: 86400 * 7,
                        version: '1.0.0',
                    },
                    message: 'ok',
                }),
            });
        }

        // ── 仪表盘统计 ──
        if (url.includes('/api/dashboard') || url.includes('/api/stats') || url.includes('/api/licenses/stats')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: {
                        total_licenses: 128,
                        active_licenses: 96,
                        expiring_soon: 12,
                        total_customers: 64,
                        revenue_this_month: 48500,
                    },
                    message: 'ok',
                }),
            });
        }

        // ── 权限管理 ──
        if (url.includes('/api/roles') || url.includes('/api/permissions')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockRoles)),
            });
        }

        // ── API 密钥 ──
        if (url.includes('/api/api-keys')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockApiKeys)),
            });
        }

        // ── 诊断 ──
        if (url.includes('/api/diagnostic')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: mockDiagnostics,
                    message: 'ok',
                }),
            });
        }

        // ── RAG 知识库 ──
        if (url.includes('/api/rag/')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: mockRagDocuments,
                    message: 'ok',
                }),
            });
        }

        // ── LLM ──
        if (url.includes('/api/llm/')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: mockLlmProviders,
                    message: 'ok',
                }),
            });
        }

        // ── 工单 ──
        if (url.includes('/api/tickets')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockTickets)),
            });
        }

        // ── 更新包 ──
        if (url.includes('/api/updates') || (url.includes('/api/products/') && url.includes('/updates'))) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockUpdatePackages)),
            });
        }

        // ── 域名 ──
        if (url.includes('/api/domains')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockDomains)),
            });
        }

        // ── 依赖安全 ──
        if (url.includes('/api/deps-security')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockVulnerabilities)),
            });
        }

        // ── 功能开关 ──
        if (url.includes('/api/feature-flags')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockFeatureFlags)),
            });
        }

        // ── 税务 ──
        if (url.includes('/api/tax/')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: mockTaxRates,
                    message: 'ok',
                }),
            });
        }

        // ── 沙箱 ──
        if (url.includes('/api/sandbox/')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: mockSandboxInfo,
                    message: 'ok',
                }),
            });
        }

        // ── Staging ──
        if (url.includes('/api/staging')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    data: mockStagingInfo,
                    message: 'ok',
                }),
            });
        }

        // ── 审计日志 ──
        if (url.includes('/api/audit-logs')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(paginated(mockAuditLogs)),
            });
        }

        // ── 广播认证 ──
        if (url.includes('/api/broadcasting/auth')) {
            return await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ auth: 'mock-auth-signature' }),
            });
        }

        // ── 其他 API ──
        return await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true, data: [], message: 'ok' }),
        });
    });
}

/**
 * 以已登录状态导航到指定页面
 * 先设置 localStorage，再设置 API mock，最后 goto
 */
const ADMIN_PREFIX = '/build';

export function adminUrl(path) {
    // 如果已经是 /build/ 开头则不处理
    if (path.startsWith('/build/') || path.startsWith('/portal/')) return path;
    // /admin/xxx → /build/xxx
    return path.replace(/^\/admin/, ADMIN_PREFIX);
}

export async function navigateAsLoggedIn(page, url, options = {}) {
    const {
        email = 'admin@huwutong.com',
        name = '超级管理员',
        roles = ['super-admin', 'admin'],
    } = options;

    // 先打开登录页让 Vue 初始化
    await page.goto(adminUrl('/admin/login'));
    await page.waitForTimeout(500);

    // 设置 localStorage 模拟登录
    const mockUser = getMockUser(email, name);
    mockUser.roles = roles;
    await page.evaluate((user) => {
        localStorage.setItem('auth_token', 'e2e-test-token');
        localStorage.setItem('user', JSON.stringify(user));
    }, mockUser);

    // 设置 API mock
    await setupApiMocks(page, { mockUser, ...options });

    // 导航到目标页
    await page.goto(adminUrl(url));
    // 等待页面初始化（缩短等待时间，让测试自己判断内容）
    await page.waitForTimeout(2000);
}
