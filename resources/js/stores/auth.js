import { defineStore } from 'pinia';
import authApi from '@/api/auth';
import tenantApi from '@/api/tenant';
import { ElMessage } from 'element-plus';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('user') || 'null'),
        token: localStorage.getItem('auth_token') || null,
        loading: false,
    }),

    getters: {
        isLoggedIn: (state) => !!state.token,
        userName: (state) => state.user?.name || '',
        userEmail: (state) => state.user?.email || '',
        avatarUrl: (state) => state.user?.avatar_url || '',
        userRoles: (state) => state.user?.roles || [],
        isAdmin: (state) => {
            const roles = state.user?.roles || [];
            return roles.some((r) => ['admin', 'super-admin'].includes(r));
        },
        // 多租户相关 getter
        tenants: (state) => state.user?.tenants || [],
        isMultiTenant: (state) => state.user?.is_multi_tenant || (state.user?.tenants?.length || 0) > 1,
        activeTenantId: (state) => state.user?.active_tenant_id || state.user?.tenant_id || null,
        activeTenantName: (state) => {
            const id = state.user?.active_tenant_id || state.user?.tenant_id;
            const tenants = state.user?.tenants || [];
            const active = tenants.find((t) => t.id === id);
            return active?.name || '';
        },
    },

    actions: {
        async login(credentials) {
            this.loading = true;
            try {
                const { data: res } = await authApi.login(credentials);
                const { user, token } = res.data;
                this.user = user;
                this.token = token;
                localStorage.setItem('auth_token', token);
                localStorage.setItem('user', JSON.stringify(user));
                ElMessage.success('登录成功');
                return true;
            } catch {
                return false;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await authApi.logout();
            } catch {
                // ignore
            }
            this.user = null;
            this.token = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
        },

        setUser(userData) {
            this.user = { ...this.user, ...userData };
            localStorage.setItem('user', JSON.stringify(this.user));
        },

        hasRole(role) {
            if (!this.user?.roles) return false;
            return this.user.roles.some(r => r.name === role || r === role);
        },

        async fetchUser() {
            try {
                const { data: res } = await authApi.user();
                this.user = res.data;
                localStorage.setItem('user', JSON.stringify(res.data));
            } catch {
                this.user = null;
                this.token = null;
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user');
            }
        },

        /**
         * 切换租户
         */
        async switchTenant(tenantId) {
            try {
                const { data: res } = await tenantApi.switchTenant(tenantId);
                // 更新本地用户数据中的租户信息
                if (this.user) {
                    this.user.active_tenant_id = res.data.active_tenant_id;
                    this.user.remember_tenant_id = tenantId;
                    localStorage.setItem('user', JSON.stringify(this.user));
                }
                ElMessage.success(`已切换到: ${res.data.tenant.name}`);
                return true;
            } catch {
                return false;
            }
        },
    },
});
