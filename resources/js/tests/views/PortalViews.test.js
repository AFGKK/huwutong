import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';
import { nextTick } from 'vue';

// ─── 通用模拟 ───

const localStorageMock = {
    store: {},
    getItem(key) { return this.store[key] || null; },
    setItem(key, value) { this.store[key] = String(value); },
    removeItem(key) { delete this.store[key]; },
    clear() { this.store = {}; },
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

vi.mock('element-plus', () => ({
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
    ElMessageBox: { confirm: vi.fn().mockResolvedValue('confirm'), alert: vi.fn(), prompt: vi.fn().mockResolvedValue({ value: 'test' }) },
    ElNotification: { success: vi.fn(), error: vi.fn() },
    ElLoading: { service: vi.fn(() => ({ close: vi.fn() })) },
}));

// 模拟 router
vi.mock('@/router', () => ({ default: { push: vi.fn() } }));
vi.mock('vue-router', () => ({
    useRoute: vi.fn(() => ({ params: { id: '1' } })),
    useRouter: vi.fn(() => ({ push: vi.fn() })),
    createRouter: vi.fn(),
    createWebHistory: vi.fn(),
}));

// 模拟 API 模块
const mockTicketList = vi.fn();
const mockTicketStats = vi.fn();
const mockTicketCreate = vi.fn();
const mockTicketCategories = vi.fn().mockResolvedValue({ data: { data: [] } });

vi.mock('@/api/ticket', () => ({
    default: {
        myTickets: (...args) => mockTicketList(...args),
        stats: (...args) => mockTicketStats(...args),
        create: (...args) => mockTicketCreate(...args),
        categories: (...args) => mockTicketCategories(...args),
    },
}));

const mockLicenseList = vi.fn();
const mockLicenseStats = vi.fn();

vi.mock('@/api/license', () => ({
    default: {
        list: (...args) => mockLicenseList(...args),
        stats: (...args) => mockLicenseStats(...args),
    },
}));

const mockDeviceList = vi.fn();
vi.mock('@/api/device', () => ({
    default: { list: (...args) => mockDeviceList(...args) },
}));

const mockBillingSubscriptions = vi.fn();
const mockBillingInvoices = vi.fn();
vi.mock('@/api/billing', () => ({
    default: {
        subscriptions: (...args) => mockBillingSubscriptions(...args),
        invoices: (...args) => mockBillingInvoices(...args),
    },
}));

const mockKbArticles = vi.fn();
vi.mock('@/api/kb', () => ({
    default: { articles: (...args) => mockKbArticles(...args) },
}));

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
    'el-statistic': { template: '<div class="el-statistic"><slot /></div>' },
    'el-skeleton': { template: '<div class="el-skeleton"><slot /></div>' },
    'el-progress': { template: '<div class="el-progress" />' },
    'el-empty': { template: '<div class="el-empty" />' },
    'el-space': { template: '<div class="el-space"><slot /></div>' },
    'el-tabs': { template: '<div class="el-tabs"><slot /></div>' },
    'el-tab-pane': { template: '<div class="el-tab-pane"><slot /></div>' },
    'el-badge': { template: '<span class="el-badge"><slot /></span>' },
    'el-tooltip': { template: '<span class="el-tooltip"><slot /></span>' },
    'el-avatar': { template: '<div class="el-avatar"><slot /></div>' },
};

// ─── Portal Licenses ───
describe('portal/Licenses.vue - 门户 License 列表', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockLicenseList.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, license_key: 'HWT-ABC', status: 'active', type: 'standard', expires_at: '2027-06-06', product: { name: 'Pro' } },
                    ],
                    total: 1,
                },
            },
        });
        mockLicenseStats.mockResolvedValue({
            data: {
                success: true,
                data: { total: 1, by_status: { active: 1 } },
            },
        });
    });

    it('组件能挂载', async () => {
        const PortalLicenses = (await import('@/views/portal/Licenses.vue')).default;
        const wrapper = mount(PortalLicenses, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载 License 列表', async () => {
        const PortalLicenses = (await import('@/views/portal/Licenses.vue')).default;
        mount(PortalLicenses, { global: { stubs: baseStubs } });
        await vi.dynamicImportSettled?.();
        await nextTick();
        expect(mockLicenseList).toHaveBeenCalled();
    });
});

// ─── Portal Tickets ───
describe('portal/Tickets.vue - 门户工单列表', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockTicketList.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
        mockTicketStats.mockResolvedValue({
            data: { success: true, data: { open: 0, in_progress: 0, resolved: 0, closed: 0 } },
        });
    });

    it('组件能挂载', async () => {
        const PortalTickets = (await import('@/views/portal/Tickets.vue')).default;
        const wrapper = mount(PortalTickets, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载工单列表', async () => {
        const PortalTickets = (await import('@/views/portal/Tickets.vue')).default;
        const wrapper = mount(PortalTickets, { global: { stubs: baseStubs } });
        await vi.dynamicImportSettled?.();
        // fetchCategories 和 fetchTickets 在 onMounted 中调用
        await new Promise(resolve => setTimeout(resolve, 500));
        await nextTick();
        // 如果 ticketApi.list 被正确模拟，此处应被调用
        // 若失败可能是组件有额外依赖导致 onMounted 未触发
        expect(mockTicketCategories).toHaveBeenCalled();
        expect(mockTicketList).toHaveBeenCalled();
    });
});

// ─── Portal Index ───
describe('portal/Index.vue - 门户首页', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockLicenseList.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
        mockLicenseStats.mockResolvedValue({
            data: { success: true, data: { total: 0, by_status: {} } },
        });
        mockTicketList.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
        mockTicketStats.mockResolvedValue({
            data: { success: true, data: { open: 0, in_progress: 0, resolved: 0, closed: 0 } },
        });
        mockDeviceList.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
    });

    it('组件能挂载', async () => {
        const PortalIndex = (await import('@/views/portal/Index.vue')).default;
        const wrapper = mount(PortalIndex, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });
});

// ─── Portal Billing ───
describe('portal/Billing.vue - 门户计费页面', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockBillingSubscriptions.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
        mockBillingInvoices.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
    });

    it('组件能挂载', async () => {
        const PortalBilling = (await import('@/views/portal/Billing.vue')).default;
        const wrapper = mount(PortalBilling, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });
});

// ─── Portal Devices ───
describe('portal/Devices.vue - 门户设备列表', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockDeviceList.mockResolvedValue({
            data: { success: true, data: { data: [], total: 0 } },
        });
    });

    it('组件能挂载', async () => {
        const PortalDevices = (await import('@/views/portal/Devices.vue')).default;
        const wrapper = mount(PortalDevices, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });
});

// ─── Portal Settings ───
describe('portal/Settings.vue - 门户设置页面', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();
    });

    it('组件能挂载', async () => {
        const PortalSettings = (await import('@/views/portal/Settings.vue')).default;
        const wrapper = mount(PortalSettings, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });
});
