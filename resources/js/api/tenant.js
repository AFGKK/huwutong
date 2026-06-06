import apiClient from './client';

export default {
    list() {
        return apiClient.get('/tenants');
    },
    switchTenant(tenantId) {
        return apiClient.post('/tenants/switch', { tenant_id: tenantId });
    },
    ssoInfo() {
        return apiClient.get('/tenants/sso-info');
    },
};
