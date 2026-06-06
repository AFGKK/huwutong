import { describe, it, expect, vi, beforeEach } from 'vitest';

const mockGet = vi.fn();
const mockPost = vi.fn();
const mockPut = vi.fn();
const mockDelete = vi.fn();

vi.mock('@/api/client', () => ({
    default: {
        get: (...args) => mockGet(...args),
        post: (...args) => mockPost(...args),
        put: (...args) => mockPut(...args),
        delete: (...args) => mockDelete(...args),
    },
}));

describe('licenseApi', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('list 发送 GET /licenses 带参数', async () => {
        const licenseApi = (await import('@/api/license')).default;
        const params = { page: 1, per_page: 20, 'filter[status]': 'active' };

        licenseApi.list(params);

        expect(mockGet).toHaveBeenCalledWith('/licenses', { params });
    });

    it('show 发送 GET /licenses/:id', async () => {
        const licenseApi = (await import('@/api/license')).default;

        licenseApi.show(42);

        expect(mockGet).toHaveBeenCalledWith('/licenses/42');
    });

    it('create 发送 POST /licenses 带数据', async () => {
        const licenseApi = (await import('@/api/license')).default;
        const data = { product_id: 1, customer_id: 2, type: 'standard', seats: 5 };

        licenseApi.create(data);

        expect(mockPost).toHaveBeenCalledWith('/licenses', data);
    });

    it('状态操作方法映射到正确端点', async () => {
        const licenseApi = (await import('@/api/license')).default;

        licenseApi.suspend(1);
        expect(mockPost).toHaveBeenCalledWith('/licenses/1/suspend');

        licenseApi.revoke(2);
        expect(mockPost).toHaveBeenCalledWith('/licenses/2/revoke');

        licenseApi.freeze(3);
        expect(mockPost).toHaveBeenCalledWith('/licenses/3/freeze');

        licenseApi.restore(4);
        expect(mockPost).toHaveBeenCalledWith('/licenses/4/restore');

        licenseApi.blacklist(5);
        expect(mockPost).toHaveBeenCalledWith('/licenses/5/blacklist');

        licenseApi.refund(6);
        expect(mockPost).toHaveBeenCalledWith('/licenses/6/refund');
    });

    it('stats 发送 GET /licenses/stats', async () => {
        const licenseApi = (await import('@/api/license')).default;

        licenseApi.stats();

        expect(mockGet).toHaveBeenCalledWith('/licenses/stats');
    });

    it('batchStore 发送 POST /licenses/batch', async () => {
        const licenseApi = (await import('@/api/license')).default;
        const data = { count: 5, product_id: 1, type: 'standard' };

        licenseApi.batchStore(data);

        expect(mockPost).toHaveBeenCalledWith('/licenses/batch', data);
    });

    it('lookup 发送 POST /licenses/lookup', async () => {
        const licenseApi = (await import('@/api/license')).default;
        const params = { license_key: 'HWT-XXX' };

        licenseApi.lookup(params);

        expect(mockPost).toHaveBeenCalledWith('/licenses/lookup', params);
    });
});

describe('productApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /products 带参数', async () => {
        const productApi = (await import('@/api/product')).default;
        productApi.list({ page: 1 });
        expect(mockGet).toHaveBeenCalledWith('/products', { params: { page: 1 } });
    });

    it('show 发送 GET /products/:id', async () => {
        const productApi = (await import('@/api/product')).default;
        productApi.show(5);
        expect(mockGet).toHaveBeenCalledWith('/products/5');
    });

    it('features 发送 GET /products/:id/features', async () => {
        const productApi = (await import('@/api/product')).default;
        productApi.features(3);
        expect(mockGet).toHaveBeenCalledWith('/products/3/features');
    });

    it('licenses 发送 GET /products/:id/licenses', async () => {
        const productApi = (await import('@/api/product')).default;
        productApi.licenses(2, { page: 1 });
        expect(mockGet).toHaveBeenCalledWith('/products/2/licenses', { params: { page: 1 } });
    });
});

describe('customerApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /customers 带参数', async () => {
        const customerApi = (await import('@/api/customer')).default;
        customerApi.list({ search: 'test' });
        expect(mockGet).toHaveBeenCalledWith('/customers', { params: { search: 'test' } });
    });

    it('create 发送 POST /customers 带数据', async () => {
        const customerApi = (await import('@/api/customer')).default;
        const data = { type: 'enterprise', level: 'pro', status: 'active' };
        customerApi.create(data);
        expect(mockPost).toHaveBeenCalledWith('/customers', data);
    });

    it('update 发送 PUT /customers/:id', async () => {
        const customerApi = (await import('@/api/customer')).default;
        customerApi.update(1, { level: 'enterprise' });
        expect(mockPut).toHaveBeenCalledWith('/customers/1', { level: 'enterprise' });
    });
});

describe('authApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('login 发送 POST /login', async () => {
        const authApi = (await import('@/api/auth')).default;
        authApi.login({ email: 'a@b.com', password: 'p' });
        expect(mockPost).toHaveBeenCalledWith('/login', { email: 'a@b.com', password: 'p' });
    });

    it('logout 发送 POST /logout', async () => {
        const authApi = (await import('@/api/auth')).default;
        authApi.logout();
        expect(mockPost).toHaveBeenCalledWith('/logout');
    });

    it('user 发送 GET /user', async () => {
        const authApi = (await import('@/api/auth')).default;
        authApi.user();
        expect(mockGet).toHaveBeenCalledWith('/user');
    });
});

describe('settingApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('grouped 发送 GET /settings', async () => {
        const settingApi = (await import('@/api/setting')).default;
        settingApi.grouped();
        expect(mockGet).toHaveBeenCalledWith('/settings');
    });

    it('update 发送 POST /settings', async () => {
        const settingApi = (await import('@/api/setting')).default;
        settingApi.update({ site_name: 'My App', site_logo: '/logo.png' });
        expect(mockPost).toHaveBeenCalledWith('/settings', {
            settings: { site_name: 'My App', site_logo: '/logo.png' },
        });
    });
});
