import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('@/api/license', () => ({
    default: {
        list: vi.fn(() => Promise.resolve({
            data: {
                success: true,
                data: [
                    { id: 1, license_key: 'HWT-001', status: 'active', expires_at: new Date(Date.now() + 86400000 * 15).toISOString() },
                    { id: 2, license_key: 'HWT-002', status: 'active', expires_at: new Date(Date.now() + 86400000 * 60).toISOString() },
                    { id: 3, license_key: 'HWT-003', status: 'expired', expires_at: new Date(Date.now() - 86400000 * 10).toISOString() },
                ],
                meta: { current_page: 1, last_page: 1, total: 3 },
            },
        })),
        stats: vi.fn(() => Promise.resolve({
            data: { success: true, data: { total: 128, active: 96 } },
        })),
    },
}));

vi.mock('@/api/customer', () => ({
    default: {
        list: vi.fn(() => Promise.resolve({
            data: {
                success: true,
                data: [
                    { id: 1, name: '客户 1', email: 'c1@test.com' },
                    { id: 2, name: '客户 2', email: 'c2@test.com' },
                ],
                meta: { current_page: 1, last_page: 1, total: 2 },
            },
        })),
        stats: vi.fn(() => Promise.resolve({
            data: { success: true, data: { total_customers: 64 } },
        })),
    },
}));

vi.mock('@/api/product', () => ({
    default: {
        list: vi.fn(() => Promise.resolve({
            data: { success: true, data: [{ id: 1, name: '产品 A' }, { id: 2, name: '产品 B' }] },
        })),
    },
}));

vi.mock('@/api/billing', () => ({
    default: {
        subscriptions: vi.fn(() => Promise.resolve({
            data: { success: true, data: [{ id: 1, plan: 'pro', status: 'active' }] },
        })),
        invoices: vi.fn(() => Promise.resolve({
            data: { success: true, data: [{ id: 1, amount: 199 }] },
        })),
        stats: vi.fn(() => Promise.resolve({
            data: { success: true, data: { revenue: 50000 } },
        })),
    },
}));

describe('useLicenseStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('starts empty', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        expect(store.licenses).toEqual([]);
        expect(store.loading).toBe(false);
        expect(store.stats).toBeNull();
    });

    it('fetchLicenses loads data', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        const result = await store.fetchLicenses();
        expect(result).toHaveLength(3);
        expect(store.total).toBe(3);
    });

    it('computed activeLicenses filters correctly', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        await store.fetchLicenses();
        expect(store.activeLicenses).toHaveLength(2);
        expect(store.activeLicenses[0].status).toBe('active');
    });

    it('computed expiringSoon finds near-expiry licenses', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        await store.fetchLicenses();
        // id=1 expires in 15 days (<=30)
        expect(store.expiringSoon).toHaveLength(1);
        expect(store.expiringSoon[0].id).toBe(1);
    });

    it('fetchStats loads stats', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        const stats = await store.fetchStats();
        expect(stats.total).toBe(128);
        expect(store.stats.total).toBe(128);
    });

    it('removeLicense removes by id', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        await store.fetchLicenses();
        store.removeLicense(1);
        expect(store.licenses).toHaveLength(2);
        expect(store.licenses.find(l => l.id === 1)).toBeUndefined();
    });

    it('addLicense prepends', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        const newLicense = { id: 99, license_key: 'HWT-NEW', status: 'active' };
        store.addLicense(newLicense);
        expect(store.licenses[0].id).toBe(99);
    });

    it('updateLicense updates matching id', async () => {
        const { useLicenseStore } = await import('@/stores/license');
        const store = useLicenseStore();
        await store.fetchLicenses();
        store.updateLicense({ id: 1, license_key: 'HWT-UPDATED' });
        expect(store.licenses[0].license_key).toBe('HWT-UPDATED');
    });
});

describe('useCustomerStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('fetchCustomers loads data', async () => {
        const { useCustomerStore } = await import('@/stores/customer');
        const store = useCustomerStore();
        const result = await store.fetchCustomers();
        expect(result).toHaveLength(2);
        expect(store.total).toBe(2);
    });

    it('removeCustomer removes by id', async () => {
        const { useCustomerStore } = await import('@/stores/customer');
        const store = useCustomerStore();
        await store.fetchCustomers();
        store.removeCustomer(1);
        expect(store.customers).toHaveLength(1);
    });
});

describe('useProductStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('fetchProducts loads data', async () => {
        const { useProductStore } = await import('@/stores/product');
        const store = useProductStore();
        const result = await store.fetchProducts();
        expect(result).toHaveLength(2);
        expect(store.products).toHaveLength(2);
    });
});

describe('useBillingStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('fetchSubscriptions loads data', async () => {
        const { useBillingStore } = await import('@/stores/billing');
        const store = useBillingStore();
        await store.fetchSubscriptions();
        expect(store.subscriptions).toHaveLength(1);
        expect(store.subscriptions[0].plan).toBe('pro');
    });

    it('fetchInvoices loads data', async () => {
        const { useBillingStore } = await import('@/stores/billing');
        const store = useBillingStore();
        await store.fetchInvoices();
        expect(store.invoices).toHaveLength(1);
        expect(store.invoices[0].amount).toBe(199);
    });

    it('fetchStats loads stats', async () => {
        const { useBillingStore } = await import('@/stores/billing');
        const store = useBillingStore();
        const stats = await store.fetchStats();
        expect(stats.revenue).toBe(50000);
    });
});
