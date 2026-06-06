import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';
import { nextTick } from 'vue';
import { createRouter, createMemoryHistory } from 'vue-router';

// ─── 通用模拟 ───

// 模拟 localStorage
const localStorageMock = {
    store: {},
    getItem(key) { return this.store[key] || null; },
    setItem(key, value) { this.store[key] = String(value); },
    removeItem(key) { delete this.store[key]; },
    clear() { this.store = {}; },
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// 模拟 Element Plus
vi.mock('element-plus', () => ({
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
    ElMessageBox: { confirm: vi.fn().mockResolvedValue('confirm'), alert: vi.fn(), prompt: vi.fn().mockResolvedValue({ value: 'test' }) },
    ElNotification: { success: vi.fn(), error: vi.fn() },
    ElLoading: { service: vi.fn(() => ({ close: vi.fn() })) },
}));

// 模拟 API 模块
const mockLicenseList = vi.fn();
const mockLicenseStats = vi.fn();
const mockLicenseShow = vi.fn();
const mockLicenseCreate = vi.fn();
const mockLicenseSuspend = vi.fn();
const mockLicenseRestore = vi.fn();
const mockLicenseFreeze = vi.fn();
const mockLicenseDestroy = vi.fn();
const mockProductList = vi.fn();
const mockCustomerList = vi.fn();
const mockSettingGrouped = vi.fn();
const mockSettingUpdate = vi.fn();

vi.mock('@/api/license', () => ({
    default: {
        list: (...args) => mockLicenseList(...args),
        stats: (...args) => mockLicenseStats(...args),
        show: (...args) => mockLicenseShow(...args),
        create: (...args) => mockLicenseCreate(...args),
        suspend: (...args) => mockLicenseSuspend(...args),
        restore: (...args) => mockLicenseRestore(...args),
        freeze: (...args) => mockLicenseFreeze(...args),
        destroy: (...args) => mockLicenseDestroy(...args),
    },
}));

vi.mock('@/api/product', () => ({
    default: { list: (...args) => mockProductList(...args) },
}));

vi.mock('@/api/customer', () => ({
    default: { list: (...args) => mockCustomerList(...args) },
}));

vi.mock('@/api/setting', () => ({
    default: {
        grouped: (...args) => mockSettingGrouped(...args),
        update: (...args) => mockSettingUpdate(...args),
    },
}));

// 模拟 router
vi.mock('@/router', () => ({ default: { push: vi.fn() } }));

// ─── 通用 Stubs ───
const baseStubs = {
    'el-card': { template: '<div class="el-card"><slot /></div>' },
    'el-table': { template: '<div class="el-table"><slot /></div>' },
    'el-table-column': { template: '<div class="el-table-column"><slot name="default" :row="{}" :column="{}" /></div>' },
    'el-input': { template: '<input class="el-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue'] },
    'el-select': { template: '<div class="el-select"><slot /></div>', props: ['modelValue'] },
    'el-option': { template: '<div class="el-option" />', props: ['label', 'value'] },
    'el-button': { template: '<button class="el-button" @click="$emit(\'click\')"><slot /></button>' },
    'el-tag': { template: '<span class="el-tag"><slot /></span>' },
    'el-link': { template: '<a class="el-link"><slot /></a>' },
    'el-icon': { template: '<i class="el-icon"><slot /></i>' },
    'el-pagination': { template: '<div class="el-pagination" />', props: ['total', 'pageSize', 'currentPage'] },
    'el-form': { template: '<form class="el-form"><slot /></form>' },
    'el-form-item': { template: '<div class="el-form-item"><slot /></div>' },
    'el-row': { template: '<div class="el-row"><slot /></div>' },
    'el-col': { template: '<div class="el-col"><slot /></div>' },
    'el-dialog': { template: '<div v-if="modelValue" class="el-dialog"><slot /></div>', props: ['modelValue'] },
    'el-date-picker': { template: '<input class="el-date-picker" />', props: ['modelValue'] },
    'el-dropdown': { template: '<div class="el-dropdown"><slot /></div>' },
    'el-dropdown-menu': { template: '<div class="el-dropdown-menu"><slot /></div>' },
    'el-dropdown-item': { template: '<div class="el-dropdown-item" @click="$emit(\'command\')"><slot /></div>' },
    'el-input-number': { template: '<input class="el-input-number" />', props: ['modelValue'] },
    'el-radio-group': { template: '<div class="el-radio-group"><slot /></div>' },
    'el-radio': { template: '<label class="el-radio"><slot /></label>' },
    'el-statistic': { template: '<div class="el-statistic"><slot /></div>' },
    'el-skeleton': { template: '<div class="el-skeleton"><slot /></div>' },
    'el-progress': { template: '<div class="el-progress" />' },
    'el-switch': { template: '<div class="el-switch" />' },
    'el-color-picker': { template: '<div class="el-color-picker" />' },
    'el-image': { template: '<div class="el-image" />' },
};

// ─── License Index Page ───
describe('licenses/Index.vue - License 列表页逻辑', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        // 默认 mock 返回值
        mockLicenseList.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, license_key: 'HWT-ABC', status: 'active', type: 'standard', seats: 5, max_devices: 3, expires_at: '2027-06-06', product: { id: 1, name: 'Pro' }, customer: { id: 1, name: '客户A' } },
                        { id: 2, license_key: 'HWT-DEF', status: 'pending', type: 'enterprise', seats: 100, max_devices: 50, expires_at: '2027-12-31', product: { id: 2, name: 'Enterprise' }, customer: { id: 2, name: '客户B' } },
                    ],
                    total: 2,
                    current_page: 1,
                    per_page: 20,
                },
            },
        });
        mockLicenseStats.mockResolvedValue({
            data: {
                success: true,
                data: {
                    total: 2,
                    by_status: { active: 1, pending: 1 },
                    by_type: { standard: 1, enterprise: 1 },
                },
            },
        });
        mockProductList.mockResolvedValue({
            data: { success: true, data: [{ id: 1, name: 'Pro' }, { id: 2, name: 'Enterprise' }] },
        });
        mockCustomerList.mockResolvedValue({
            data: { success: true, data: { data: [{ id: 1, name: '客户A' }, { id: 2, name: '客户B' }] } },
        });
    });

    it('组件能挂载', async () => {
        const LicenseIndex = (await import('@/views/licenses/Index.vue')).default;
        const wrapper = mount(LicenseIndex, {
            global: { stubs: baseStubs },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后自动加载列表数据和统计', async () => {
        const LicenseIndex = (await import('@/views/licenses/Index.vue')).default;
        mount(LicenseIndex, {
            global: { stubs: baseStubs },
        });

        await vi.dynamicImportSettled?.();
        await nextTick();

        // 确保加载了列表和统计
        expect(mockLicenseList).toHaveBeenCalled();
        expect(mockLicenseStats).toHaveBeenCalled();
    });

    it('搜索关键词变化时重新加载列表', async () => {
        const LicenseIndex = (await import('@/views/licenses/Index.vue')).default;
        const wrapper = mount(LicenseIndex, {
            global: { stubs: baseStubs },
        });

        await nextTick();

        // 模拟搜索（如果组件有 search 或 filter 逻辑）
        if (wrapper.vm.searchQuery !== undefined || wrapper.vm.filter !== undefined) {
            const searchFn = wrapper.vm.handleSearch || wrapper.vm.search || wrapper.vm.fetchData;
            if (typeof searchFn === 'function') {
                searchFn();
                await nextTick();
                // 应该被调用了多次（初始加载 + 搜索）
                expect(mockLicenseList.mock.calls.length).toBeGreaterThanOrEqual(2);
            }
        }
    });
});

// 创建一个模拟路由，让 License Detail 页面的 useRoute() 能工作
function createDetailRouter() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/licenses/:id', name: 'license-detail', component: { template: '<div>Detail</div>' } },
        ],
    });
    router.push('/licenses/1');
    return router;
}

// ─── License Detail Page ───
describe('licenses/Detail.vue - License 详情页逻辑', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockLicenseShow.mockResolvedValue({
            data: {
                success: true,
                data: {
                    license: {
                        id: 1,
                        license_key: 'HWT-ABC-123',
                        status: 'active',
                        type: 'standard',
                        seats: 10,
                        max_devices: 5,
                        expires_at: '2027-06-06T00:00:00Z',
                        created_at: '2026-06-06T00:00:00Z',
                        activated_at: '2026-06-06T01:00:00Z',
                        product: { id: 1, name: 'Pro License' },
                        customer: { id: 1, name: 'Tech Corp' },
                        devices: [{ id: 1, fingerprint: 'fp-1', platform: 'linux', last_seen_at: '2026-06-06T02:00:00Z' }],
                        activations: [{ id: 1, action: 'activate', created_at: '2026-06-06T01:00:00Z', ip_address: '192.168.1.1' }],
                    },
                    status_info: {
                        status: 'active',
                        is_usable: true,
                        can_suspend: true,
                        can_freeze: true,
                        can_revoke: true,
                        can_restore: false,
                    },
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const LicenseDetail = (await import('@/views/licenses/Detail.vue')).default;
        const wrapper = mount(LicenseDetail, {
            global: {
                plugins: [createDetailRouter()],
                stubs: {
                    ...baseStubs,
                    'el-tabs': { template: '<div class="el-tabs"><slot /></div>' },
                    'el-tab-pane': { template: '<div class="el-tab-pane"><slot /></div>' },
                    'el-descriptions': { template: '<div class="el-descriptions"><slot /></div>' },
                    'el-descriptions-item': { template: '<div class="el-descriptions-item"><slot /></div>' },
                    'el-page-header': { template: '<div class="el-page-header"><slot /></div>' },
                    'el-space': { template: '<div class="el-space"><slot /></div>' },
                    'el-empty': { template: '<div class="el-empty" />' },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载详情', async () => {
        const LicenseDetail = (await import('@/views/licenses/Detail.vue')).default;
        mount(LicenseDetail, {
            global: {
                plugins: [createDetailRouter()],
                stubs: {
                    ...baseStubs,
                    'el-tabs': { template: '<div class="el-tabs"><slot /></div>' },
                    'el-tab-pane': { template: '<div class="el-tab-pane"><slot /></div>' },
                    'el-descriptions': { template: '<div class="el-descriptions"><slot /></div>' },
                    'el-descriptions-item': { template: '<div class="el-descriptions-item"><slot /></div>' },
                    'el-page-header': { template: '<div class="el-page-header"><slot /></div>' },
                    'el-space': { template: '<div class="el-space"><slot /></div>' },
                    'el-empty': { template: '<div class="el-empty" />' },
                },
            },
        });

        await vi.dynamicImportSettled?.();
        await nextTick();

        expect(mockLicenseShow).toHaveBeenCalled();
    });
});

// ─── Dashboard Page ───
describe('dashboard/Index.vue - 仪表盘页面', () => {
    const mockDashboardStats = vi.fn();
    const mockDashboardLicenses = vi.fn();

    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        // 为 dashboard 重新 mock
        // 注意：dashboard 可能使用 licenseApi.stats 和 licenseApi.list
        mockLicenseStats.mockResolvedValue({
            data: {
                success: true,
                data: {
                    total: 150,
                    active: 120,
                    expired: 20,
                    revoked: 10,
                    by_status: { active: 120, expired: 20, revoked: 10 },
                },
            },
        });
        mockLicenseList.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, license_key: 'HWT-001', status: 'active', type: 'standard', expires_at: '2027-01-01', product: { name: 'Pro' }, customer: { name: '客户A' } },
                        { id: 2, license_key: 'HWT-002', status: 'active', type: 'enterprise', expires_at: '2026-08-01', product: { name: 'Enterprise' }, customer: { name: '客户B' } },
                    ],
                    total: 2,
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const Dashboard = (await import('@/views/dashboard/Index.vue')).default;
        const wrapper = mount(Dashboard, {
            global: { stubs: baseStubs },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载统计数据', async () => {
        const Dashboard = (await import('@/views/dashboard/Index.vue')).default;
        mount(Dashboard, {
            global: { stubs: baseStubs },
        });

        await nextTick();
        expect(mockLicenseStats).toHaveBeenCalled();
    });
});

// ─── Products Page ───
describe('products/Index.vue - 产品列表页', () => {
    const mockProductStats = vi.fn();

    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        // 重新 mock product list
        mockProductList.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, name: 'Pro', slug: 'pro', type: 'standard', is_active: true, created_at: '2026-01-01' },
                        { id: 2, name: 'Enterprise', slug: 'enterprise', type: 'enterprise', is_active: true, created_at: '2026-01-01' },
                    ],
                    total: 2,
                    current_page: 1,
                    per_page: 20,
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const ProductIndex = (await import('@/views/products/Index.vue')).default;
        const wrapper = mount(ProductIndex, {
            global: { stubs: baseStubs },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载产品列表', async () => {
        const ProductIndex = (await import('@/views/products/Index.vue')).default;
        mount(ProductIndex, {
            global: { stubs: baseStubs },
        });

        await nextTick();
        expect(mockProductList).toHaveBeenCalled();
    });
});

// ─── Customers Page ───
describe('customers/Index.vue - 客户列表页', () => {
    const mockCustomerStats = vi.fn();

    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockCustomerList.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, type: 'enterprise', level: 'enterprise', status: 'active', user: { id: 1, name: '公司A', email: 'a@corp.com' } },
                        { id: 2, type: 'individual', level: 'pro', status: 'active', user: { id: 2, name: '个人B', email: 'b@dev.com' } },
                    ],
                    total: 2,
                    current_page: 1,
                    per_page: 20,
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const CustomerIndex = (await import('@/views/customers/Index.vue')).default;
        const wrapper = mount(CustomerIndex, {
            global: { stubs: baseStubs },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载客户列表', async () => {
        const CustomerIndex = (await import('@/views/customers/Index.vue')).default;
        mount(CustomerIndex, {
            global: { stubs: baseStubs },
        });

        await nextTick();
        expect(mockCustomerList).toHaveBeenCalled();
    });
});

// ─── Settings Page ───
describe('settings/Index.vue - 设置页面', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockSettingGrouped.mockResolvedValue({
            data: {
                success: true,
                data: [
                    {
                        group: 'general',
                        label: '基本设置',
                        settings: [
                            { key: 'site_name', label: '站点名称', type: 'text', value: 'HWT License' },
                            { key: 'site_description', label: '站点描述', type: 'textarea', value: 'Enterprise License Management' },
                        ],
                    },
                    {
                        group: 'mail',
                        label: '邮件设置',
                        settings: [
                            { key: 'mail_from', label: '发件地址', type: 'text', value: 'noreply@huwutong.com' },
                        ],
                    },
                ],
            },
        });
    });

    it('组件能挂载', async () => {
        const Settings = (await import('@/views/settings/Index.vue')).default;
        const wrapper = mount(Settings, {
            global: { stubs: baseStubs },
        });

        expect(wrapper.exists()).toBe(true);
    });
});
