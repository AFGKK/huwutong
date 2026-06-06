import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { mount } from '@vue/test-utils';

// 模拟 API 模块以避免网络请求
vi.mock('@/api/license', () => ({
    default: {
        list: vi.fn().mockResolvedValue({ data: { success: true, data: { data: [], total: 0 } } }),
        show: vi.fn().mockResolvedValue({ data: { success: true, data: { id: 1, license_key: 'test', status: 'active' } } }),
    },
}));

vi.mock('@/api/customer', () => ({
    default: {
        list: vi.fn().mockResolvedValue({ data: { success: true, data: { data: [], total: 0 } } }),
    },
}));

vi.mock('@/api/product', () => ({
    default: {
        list: vi.fn().mockResolvedValue({ data: { success: true, data: { data: [] } } }),
    },
}));

vi.mock('@/api/dashboard', () => ({
    default: {
        stats: vi.fn().mockResolvedValue({
            data: { success: true, data: { total_licenses: 0, active_licenses: 0, revoked_licenses: 0, total_customers: 0, licenses: [], recent_activity: [] } },
        }),
    },
}));

vi.mock('@/api/notification', () => ({
    default: {
        list: vi.fn().mockResolvedValue({ data: { success: true, data: { data: [] } } }),
        unreadCount: vi.fn().mockResolvedValue({ data: { success: true, data: { count: 0 } } }),
    },
}));

vi.mock('@/api/system', () => ({
    default: {
        health: vi.fn().mockResolvedValue({
            data: { success: true, data: { status: 'ok', checks: { database: { status: 'ok' }, cache: { status: 'ok' } } } },
        }),
    },
}));

// 创建一个简单的可注入路由模拟
// useRoute 和 useRouter 使用 Vue Router 的全局符号注入
// 我们通过 global.plugins 提供模拟路由
import { createRouter, createMemoryHistory } from 'vue-router';

// 安全导入 — 如果 vue-router 未提供，则静默降级
let mockRouterInstance;
try {
    mockRouterInstance = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
            { path: '/licenses/:id', name: 'license-detail', component: { template: '<div>Detail</div>' } },
        ],
    });
    // 预置路由状态
    mockRouterInstance.push('/licenses/1');
} catch (e) {
    // fallback: 如果 vue-router 不可用, 不使用 plugin
    mockRouterInstance = null;
}

function createGlobalPlugins() {
    if (mockRouterInstance) {
        return [mockRouterInstance];
    }
    return [];
}

describe('License View Components', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('license index mounts successfully', async () => {
        const LicenseIndex = (await import('@/views/licenses/Index.vue')).default;
        const wrapper = mount(LicenseIndex, {
            global: {
                plugins: createGlobalPlugins(),
                stubs: {
                    'el-card': { template: '<div><slot /></div>' },
                    'el-table': { template: '<div><slot /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-input': { template: '<input />' },
                    'el-select': { template: '<div><slot /></div>' },
                    'el-option': { template: '<div />' },
                    'el-button': { template: '<button><slot /></button>' },
                    'el-tag': { template: '<span><slot /></span>' },
                    'el-link': { template: '<a><slot /></a>' },
                    'el-icon': { template: '<i><slot /></i>' },
                    'el-pagination': { template: '<div />' },
                    'el-form': { template: '<form><slot /></form>' },
                    'el-form-item': { template: '<div><slot /></div>' },
                    'el-row': { template: '<div><slot /></div>' },
                    'el-col': { template: '<div><slot /></div>' },
                    'el-dialog': { template: '<div v-if="modelValue"><slot /></div>', props: ['modelValue'] },
                    'el-date-picker': { template: '<input />' },
                    'el-dropdown': { template: '<div><slot /></div>' },
                    'el-dropdown-menu': { template: '<div><slot /></div>' },
                    'el-dropdown-item': { template: '<div><slot /></div>' },
                    'el-input-number': { template: '<input />' },
                    'el-radio-group': { template: '<div><slot /></div>' },
                    'el-radio': { template: '<div><slot /></div>' },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('license detail mounts successfully', async () => {
        const LicenseDetail = (await import('@/views/licenses/Detail.vue')).default;
        const wrapper = mount(LicenseDetail, {
            global: {
                plugins: createGlobalPlugins(),
                stubs: {
                    'el-card': { template: '<div><slot /></div>' },
                    'el-tabs': { template: '<div><slot /></div>' },
                    'el-tab-pane': { template: '<div><slot /></div>' },
                    'el-descriptions': { template: '<div><slot /></div>' },
                    'el-descriptions-item': { template: '<div><slot /></div>' },
                    'el-tag': { template: '<span><slot /></span>' },
                    'el-button': { template: '<button><slot /></button>' },
                    'el-table': { template: '<div><slot /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-skeleton': { template: '<div><slot /></div>' },
                    'el-empty': { template: '<div />' },
                    'el-icon': { template: '<i><slot /></i>' },
                    'el-page-header': { template: '<div><slot /></div>' },
                    'el-space': { template: '<div><slot /></div>' },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });
});

describe('Dashboard View Component', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('dashboard mounts successfully', async () => {
        const Dashboard = (await import('@/views/dashboard/Index.vue')).default;
        const wrapper = mount(Dashboard, {
            global: {
                plugins: createGlobalPlugins(),
                stubs: {
                    'el-row': { template: '<div><slot /></div>' },
                    'el-col': { template: '<div><slot /></div>' },
                    'el-card': { template: '<div><slot /></div>' },
                    'el-statistic': { template: '<div><slot /></div>' },
                    'el-table': { template: '<div><slot /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-tag': { template: '<span><slot /></span>' },
                    'el-icon': { template: '<i><slot /></i>' },
                    'el-skeleton': { template: '<div><slot /></div>' },
                    'el-button': { template: '<button><slot /></button>' },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });
});

describe('Customer View Component', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('customer index mounts successfully', async () => {
        const CustomerIndex = (await import('@/views/customers/Index.vue')).default;
        const wrapper = mount(CustomerIndex, {
            global: {
                plugins: createGlobalPlugins(),
                stubs: {
                    'el-card': { template: '<div><slot /></div>' },
                    'el-table': { template: '<div><slot /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-table-column': { template: '<div><slot name="default" :row="{}" :column="{}" /></div>' },
                    'el-input': { template: '<input />' },
                    'el-button': { template: '<button><slot /></button>' },
                    'el-icon': { template: '<i><slot /></i>' },
                    'el-tag': { template: '<span><slot /></span>' },
                    'el-link': { template: '<a><slot /></a>' },
                    'el-pagination': { template: '<div />' },
                    'el-form': { template: '<form><slot /></form>' },
                    'el-form-item': { template: '<div><slot /></div>' },
                    'el-select': { template: '<div><slot /></div>' },
                    'el-option': { template: '<div />' },
                    'el-row': { template: '<div><slot /></div>' },
                    'el-col': { template: '<div><slot /></div>' },
                    'el-dropdown': { template: '<div><slot /></div>' },
                    'el-dropdown-menu': { template: '<div><slot /></div>' },
                    'el-dropdown-item': { template: '<div><slot /></div>' },
                    'el-radio-group': { template: '<div><slot /></div>' },
                    'el-radio': { template: '<div><slot /></div>' },
                    'el-dialog': { template: '<div v-if="modelValue"><slot /></div>', props: ['modelValue'] },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });
});

describe('System Health View Component', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('health page mounts successfully', async () => {
        const Health = (await import('@/views/system/Health.vue')).default;
        const wrapper = mount(Health, {
            global: {
                plugins: createGlobalPlugins(),
                stubs: {
                    'el-card': { template: '<div><slot /></div>' },
                    'el-descriptions': { template: '<div><slot /></div>' },
                    'el-descriptions-item': { template: '<div><slot /></div>' },
                    'el-tag': { template: '<span><slot /></span>' },
                    'el-icon': { template: '<i><slot /></i>' },
                    'el-button': { template: '<button><slot /></button>' },
                    'el-row': { template: '<div><slot /></div>' },
                    'el-col': { template: '<div><slot /></div>' },
                    'el-switch': { template: '<div><slot /></div>' },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });
});
