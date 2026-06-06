import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';

describe('useAuthStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('starts with no user and no token', () => {
        const store = useAuthStore();
        expect(store.user).toBeNull();
        expect(store.token).toBeNull();
        expect(store.isLoggedIn).toBe(false);
    });

    it('has correct initial getters', () => {
        const store = useAuthStore();

        expect(store.userName).toBe('');
        expect(store.userEmail).toBe('');
        expect(store.userRoles).toEqual([]);
        expect(store.isAdmin).toBe(false);
        expect(store.tenants).toEqual([]);
        expect(store.isMultiTenant).toBe(false);
        expect(store.activeTenantId).toBeNull();
    });

    it('has empty tenant info when no user', () => {
        const store = useAuthStore();
        expect(store.tenants).toEqual([]);
        expect(store.isMultiTenant).toBe(false);
        expect(store.activeTenantName).toBe('');
    });

    it('restores state from localStorage', () => {
        const userData = {
            id: 1,
            name: 'Existing User',
            email: 'user@test.com',
            roles: ['admin'],
            tenants: [
                { id: 1, name: 'My Tenant', slug: 'my-tenant' },
            ],
            is_multi_tenant: false,
            active_tenant_id: 1,
        };

        localStorage.setItem('auth_token', 'existing-token');
        localStorage.setItem('user', JSON.stringify(userData));

        const store = useAuthStore();

        expect(store.token).toBe('existing-token');
        expect(store.isLoggedIn).toBe(true);
        expect(store.userName).toBe('Existing User');
        expect(store.userEmail).toBe('user@test.com');
        expect(store.tenants).toHaveLength(1);
        expect(store.activeTenantName).toBe('My Tenant');
    });

    it('handles multi-tenant correctly', () => {
        const userData = {
            id: 1,
            name: 'Multi Tenant User',
            email: 'multi@test.com',
            tenants: [
                { id: 1, name: 'Tenant A' },
                { id: 2, name: 'Tenant B' },
            ],
            is_multi_tenant: true,
            active_tenant_id: 2,
        };

        localStorage.setItem('auth_token', 'token');
        localStorage.setItem('user', JSON.stringify(userData));

        const store = useAuthStore();

        expect(store.isMultiTenant).toBe(true);
        expect(store.activeTenantId).toBe(2);
        expect(store.activeTenantName).toBe('Tenant B');
        expect(store.tenants).toHaveLength(2);
    });

    it('userRoles returns empty array when no user', () => {
        const store = useAuthStore();
        expect(store.userRoles).toEqual([]);
        expect(store.isAdmin).toBe(false);
    });

    it('isAdmin detects admin role', () => {
        const userData = {
            id: 1,
            name: 'Admin User',
            email: 'admin@test.com',
            roles: ['admin', 'super-admin'],
            tenants: [{ id: 1, name: 'Tenant' }],
        };

        localStorage.setItem('auth_token', 'token');
        localStorage.setItem('user', JSON.stringify(userData));

        const store = useAuthStore();
        expect(store.isAdmin).toBe(true);
    });
});
