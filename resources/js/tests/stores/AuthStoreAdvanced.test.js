import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// 模拟 localStorage
const localStorageMock = {
    store: {},
    getItem(key) { return this.store[key] || null; },
    setItem(key, value) { this.store[key] = String(value); },
    removeItem(key) { delete this.store[key]; },
    clear() { this.store = {}; },
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// 模拟 router
vi.mock('@/router', () => ({
    default: {
        push: vi.fn(),
        replace: vi.fn(),
    },
}));

// 模拟 Element Plus
vi.mock('element-plus', () => ({
    ElMessage: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
        info: vi.fn(),
    },
    ElMessageBox: {
        confirm: vi.fn().mockResolvedValue(true),
        alert: vi.fn(),
        prompt: vi.fn(),
    },
}));

describe('useAuthStore - 完整认证流程', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();
    });

    it('初始状态正确', async () => {
        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();
        expect(store.user).toBeNull();
        expect(store.token).toBeNull();
        expect(store.loading).toBe(false);
        expect(store.isLoggedIn).toBe(false);
    });

    it('login 成功后更新状态和 localStorage', async () => {
        const authApi = await import('@/api/auth');
        authApi.default.login = vi.fn().mockResolvedValue({
            data: {
                success: true,
                data: {
                    user: { id: 1, name: 'Test', email: 'test@test.com', roles: ['admin'] },
                    token: 'fake-jwt-token',
                },
            },
        });

        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();
        const result = await store.login({ email: 'test@test.com', password: 'secret' });

        expect(result).toBe(true);
        expect(store.isLoggedIn).toBe(true);
        expect(store.token).toBe('fake-jwt-token');
        expect(store.userName).toBe('Test');
        expect(localStorage.getItem('auth_token')).toBe('fake-jwt-token');
    });

    it('login 失败时状态不变', async () => {
        const authApi = await import('@/api/auth');
        authApi.default.login = vi.fn().mockRejectedValue(new Error('Invalid credentials'));

        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();
        const result = await store.login({ email: 'bad@test.com', password: 'wrong' });

        expect(result).toBe(false);
        expect(store.isLoggedIn).toBe(false);
        expect(store.token).toBeNull();
    });

    it('logout 清除状态和 localStorage', async () => {
        localStorage.setItem('auth_token', 'token');
        localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Test' }));

        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();

        await store.logout();

        expect(store.token).toBeNull();
        expect(store.user).toBeNull();
        expect(localStorage.getItem('auth_token')).toBeNull();
        expect(localStorage.getItem('user')).toBeNull();
    });

    it('fetchUser 成功时更新用户数据', async () => {
        const authApi = await import('@/api/auth');
        authApi.default.user = vi.fn().mockResolvedValue({
            data: { success: true, data: { id: 2, name: 'Fetched', email: 'fetched@test.com', roles: ['user'] } },
        });

        localStorage.setItem('auth_token', 'token');
        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();

        await store.fetchUser();

        expect(store.userName).toBe('Fetched');
        expect(store.userEmail).toBe('fetched@test.com');
    });

    it('fetchUser 失败时清除状态', async () => {
        const authApi = await import('@/api/auth');
        authApi.default.user = vi.fn().mockRejectedValue(new Error('Unauthorized'));

        localStorage.setItem('auth_token', 'expired-token');
        localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Old' }));

        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();

        await store.fetchUser();

        expect(store.token).toBeNull();
        expect(store.user).toBeNull();
        expect(localStorage.getItem('auth_token')).toBeNull();
    });

    it('isAdmin 正确检测 admin/super-admin 角色', async () => {
        const { useAuthStore } = await import('@/stores/auth');

        // 每次测试使用独立的 store 上下文
        function createStoreWithAuth(token, userData) {
            if (token) localStorage.setItem('auth_token', token);
            if (userData) localStorage.setItem('user', JSON.stringify(userData));
            setActivePinia(createPinia());
            return useAuthStore();
        }

        // 无角色
        expect(createStoreWithAuth(null, null).isAdmin).toBe(false);

        // admin 角色
        expect(createStoreWithAuth('t', { id: 1, name: 'A', roles: ['admin'] }).isAdmin).toBe(true);

        // super-admin 角色
        expect(createStoreWithAuth('t', { id: 1, name: 'B', roles: ['super-admin'] }).isAdmin).toBe(true);

        // user 角色
        expect(createStoreWithAuth('t', { id: 1, name: 'C', roles: ['user'] }).isAdmin).toBe(false);
    });

    it('多租户 getter 正常', async () => {
        localStorage.setItem('auth_token', 't');
        localStorage.setItem('user', JSON.stringify({
            id: 1,
            name: 'Multi',
            tenants: [
                { id: 1, name: 'Tenant A', slug: 'a' },
                { id: 2, name: 'Tenant B', slug: 'b' },
            ],
            is_multi_tenant: true,
            active_tenant_id: 2,
        }));

        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();

        expect(store.tenants).toHaveLength(2);
        expect(store.isMultiTenant).toBe(true);
        expect(store.activeTenantId).toBe(2);
        expect(store.activeTenantName).toBe('Tenant B');
    });

    it('switchTenant 更新 active_tenant_id', async () => {
        const tenantApi = await import('@/api/tenant');
        tenantApi.default.switchTenant = vi.fn().mockResolvedValue({
            data: {
                success: true,
                data: {
                    active_tenant_id: 3,
                    tenant: { id: 3, name: 'Tenant C' },
                },
            },
        });

        localStorage.setItem('auth_token', 't');
        localStorage.setItem('user', JSON.stringify({
            id: 1,
            name: 'Switcher',
            tenants: [{ id: 3, name: 'Tenant C' }],
            active_tenant_id: 1,
        }));

        const { useAuthStore } = await import('@/stores/auth');
        const store = useAuthStore();

        const result = await store.switchTenant(3);
        expect(result).toBe(true);
        expect(store.activeTenantId).toBe(3);
    });
});
