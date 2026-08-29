import { defineStore } from 'pinia';
import authApi from '@/api/auth';
import tenantApi from '@/api/tenant';
import { ElMessage } from 'element-plus';
import i18n from '@/i18n';

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
        applySession(user, token) {
            this.user = user;
            this.token = token;
            localStorage.setItem('auth_token', token);
            localStorage.setItem('user', JSON.stringify(user));
            import('@/echo').then(({ refreshEchoAuthHeaders }) => refreshEchoAuthHeaders()).catch(() => {});
        },

        async login(credentials) {
            this.loading = true;
            try {
                const { data: res } = await authApi.login(credentials);
                const { user, token } = res.data;
                this.applySession(user, token);
                ElMessage.success(i18n.global.t('auth.login_success'));
                return true;
            } catch (e) {
                const errData = e?.response?.data?.error;
                if (errData?.details) {
                    const firstMsg = Object.values(errData.details)[0]?.[0];
                    if (firstMsg) ElMessage.error(firstMsg);
                } else {
                    ElMessage.error(errData?.message || i18n.global.t('auth.login_fail'));
                }
                return false;
            } finally {
                this.loading = false;
            }
        },

        async phoneLogin(payload) {
            this.loading = true;
            try {
                const { data: res } = await authApi.phoneLogin(payload);
                const { user, token } = res.data;
                this.applySession(user, token);
                ElMessage.success(i18n.global.t('auth.login_success'));
                return true;
            } catch (e) {
                ElMessage.error(e?.response?.data?.error?.message || e?.response?.data?.message || i18n.global.t('auth.phone_login_fail'));
                return false;
            } finally {
                this.loading = false;
            }
        },

        async phoneRegister(payload) {
            this.loading = true;
            try {
                const { data: res } = await authApi.phoneRegister(payload);
                const payloadData = res.data || res;
                const user = payloadData.user;
                const token = payloadData.token;
                if (user && token) {
                    this.applySession(user, token);
                }
                ElMessage.success(i18n.global.t('auth.register_success'));
                return { ok: true, data: payloadData };
            } catch (e) {
                ElMessage.error(e?.response?.data?.error?.message || e?.response?.data?.message || i18n.global.t('auth.register_fail'));
                return { ok: false };
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
                ElMessage.success(i18n.global.t('auth.tenant_switched', { name: res.data.tenant.name }));
                return true;
            } catch {
                return false;
            }
        },
    },
});
