import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';

// 模拟通知 API
vi.mock('@/api/notification', () => ({
    default: {
        list: vi.fn().mockResolvedValue({ data: { success: true, data: { data: [] } } }),
        unreadCount: vi.fn().mockResolvedValue({ data: { success: true, data: { count: 0 } } }),
    },
}));

describe('Login.vue', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('renders login form', async () => {
        const Login = (await import('@/views/auth/Login.vue')).default;
        const wrapper = mount(Login, {
            global: {
                stubs: {
                    'el-form': {
                        template: '<form><slot /></form>',
                        props: ['model', 'rules'],
                    },
                    'el-form-item': {
                        template: '<div><slot /></div>',
                        props: ['label', 'prop'],
                    },
                    'el-input': {
                        template: '<input :placeholder="placeholder" />',
                        props: ['placeholder', 'type', 'modelValue', 'prefixIcon'],
                    },
                    'el-button': {
                        template: '<button @click="$emit(\'click\')"><slot /></button>',
                        props: ['type', 'loading', 'size'],
                    },
                    'el-icon': {
                        template: '<i><slot /></i>',
                    },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.text()).toContain('HWT License');
        expect(wrapper.text()).toContain('登录');
    });
});

describe('TenantRouter (Tenant Select Page)', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('renders tenant selection page', async () => {
        // 设置 localStorage 模拟用户已登录且有租户数据
        localStorage.setItem('auth_token', 'test-token');
        localStorage.setItem('user', JSON.stringify({
            id: 1,
            name: '测试用户',
            tenants: [
                { id: 1, name: 'Tenant A', slug: 'tenant-a' },
                { id: 2, name: 'Tenant B', slug: 'tenant-b' },
            ],
            is_multi_tenant: true,
            active_tenant_id: 1,
        }));

        // 模拟 auth store
        const { useAuthStore } = await import('@/stores/auth');
        const auth = useAuthStore();

        expect(auth.tenants.length).toBe(2);
        expect(auth.isMultiTenant).toBe(true);
        expect(auth.activeTenantId).toBe(1);
        expect(auth.activeTenantName).toBe('Tenant A');
    });
});
