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
const mockBillingSubscriptions = vi.fn();
const mockBillingStats = vi.fn();
const mockBillingInvoices = vi.fn();
const mockBillingInvoiceStats = vi.fn();
const mockBillingCreateSubscription = vi.fn();
const mockBillingGetSubscription = vi.fn();
const mockBillingChangePlan = vi.fn();
const mockBillingCancelSubscription = vi.fn();
const mockBillingResumeSubscription = vi.fn();
const mockBillingManualRenew = vi.fn();
const mockBillingGetInvoice = vi.fn();
const mockBillingMarkInvoicePaid = vi.fn();

vi.mock('@/api/billing', () => ({
    default: {
        subscriptions: (...args) => mockBillingSubscriptions(...args),
        stats: (...args) => mockBillingStats(...args),
        invoices: (...args) => mockBillingInvoices(...args),
        invoiceStats: (...args) => mockBillingInvoiceStats(...args),
        createSubscription: (...args) => mockBillingCreateSubscription(...args),
        getSubscription: (...args) => mockBillingGetSubscription(...args),
        changePlan: (...args) => mockBillingChangePlan(...args),
        cancelSubscription: (...args) => mockBillingCancelSubscription(...args),
        resumeSubscription: (...args) => mockBillingResumeSubscription(...args),
        manualRenew: (...args) => mockBillingManualRenew(...args),
        getInvoice: (...args) => mockBillingGetInvoice(...args),
        markInvoicePaid: (...args) => mockBillingMarkInvoicePaid(...args),
    },
}));

// 模拟 router
vi.mock('@/router', () => ({ default: { push: vi.fn() } }));
vi.mock('vue-router', () => ({
    useRoute: vi.fn(() => ({ params: { id: '1' } })),
    useRouter: vi.fn(() => ({ push: vi.fn() })),
    createRouter: vi.fn(),
    createWebHistory: vi.fn(),
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
    'el-empty': { template: '<div class="el-empty" />' },
    'el-space': { template: '<div class="el-space"><slot /></div>' },
    'el-tabs': { template: '<div class="el-tabs"><slot /></div>' },
    'el-tab-pane': { template: '<div class="el-tab-pane"><slot /></div>' },
};

// ─── Billing Index ───
describe('billing/Index.vue - Billing 订阅管理页逻辑', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockBillingSubscriptions.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, plan: 'standard', price: 99, status: 'active', billing_period: 'monthly', auto_renew: true, customer: { id: 1, name: '客户A' }, product: { id: 1, name: 'Pro' } },
                        { id: 2, plan: 'enterprise', price: 599, status: 'grace', billing_period: 'yearly', auto_renew: true, customer: { id: 2, name: '客户B' }, product: { id: 2, name: 'Enterprise' } },
                    ],
                    total: 2,
                    current_page: 1,
                    per_page: 20,
                },
            },
        });
        mockBillingStats.mockResolvedValue({
            data: {
                success: true,
                data: {
                    total: 5,
                    active: 3,
                    grace: 1,
                    mrr: 2997,
                    estimated_arr: 35964,
                },
            },
        });
        mockBillingInvoices.mockResolvedValue({
            data: {
                success: true,
                data: {
                    data: [
                        { id: 1, invoice_no: 'INV-001', amount: 99, status: 'pending', currency: 'CNY' },
                        { id: 2, invoice_no: 'INV-002', amount: 599, status: 'paid', currency: 'CNY', paid_at: '2026-06-01' },
                    ],
                    total: 2,
                    current_page: 1,
                    per_page: 20,
                },
            },
        });
        mockBillingInvoiceStats.mockResolvedValue({
            data: {
                success: true,
                data: {
                    total: 10,
                    paid: 7,
                    pending: 2,
                    overdue: 1,
                    total_amount: 5000,
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const BillingIndex = (await import('@/views/billing/Index.vue')).default;
        const wrapper = mount(BillingIndex, {
            global: { stubs: baseStubs },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载订阅列表、统计、发票数据', async () => {
        const BillingIndex = (await import('@/views/billing/Index.vue')).default;
        mount(BillingIndex, {
            global: { stubs: baseStubs },
        });

        await vi.dynamicImportSettled?.();
        await nextTick();

        expect(mockBillingSubscriptions).toHaveBeenCalled();
        expect(mockBillingStats).toHaveBeenCalled();
        expect(mockBillingInvoices).toHaveBeenCalled();
    });

    it('标题包含计费管理', async () => {
        const BillingIndex = (await import('@/views/billing/Index.vue')).default;
        const wrapper = mount(BillingIndex, {
            global: { stubs: baseStubs },
        });

        await nextTick();
        expect(wrapper.text()).toContain('计费');
    });
});

// ─── Billing Detail ───
describe('billing/Detail.vue - 订阅详情页逻辑', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockBillingGetSubscription.mockResolvedValue({
            data: {
                success: true,
                data: {
                    id: 1,
                    plan: 'standard',
                    price: 99,
                    status: 'active',
                    billing_period: 'monthly',
                    auto_renew: true,
                    starts_at: '2026-01-01',
                    ends_at: '2026-07-01',
                    customer: { id: 1, name: '客户A', email: 'a@test.com' },
                    product: { id: 1, name: 'Pro License' },
                    invoices: [
                        { id: 1, invoice_no: 'INV-001', amount: 99, status: 'paid' },
                    ],
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const BillingDetail = (await import('@/views/billing/Detail.vue')).default;
        const wrapper = mount(BillingDetail, {
            global: {
                stubs: {
                    ...baseStubs,
                    'el-descriptions': { template: '<div class="el-descriptions"><slot /></div>' },
                    'el-descriptions-item': { template: '<div class="el-descriptions-item"><slot /></div>' },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });
});
