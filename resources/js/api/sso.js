import apiClient from './client';

const BASE = '/sso';

export default {
    providers() {
        return apiClient.get(`${BASE}/providers`);
    },
    configure(data) {
        return apiClient.post(`${BASE}/providers`, data);
    },
    toggle(id, isActive) {
        return apiClient.post(`${BASE}/providers/${id}/toggle`, { is_active: isActive });
    },
    loginUrl(id) {
        return apiClient.get(`${BASE}/providers/${id}/login-url`);
    },
    connections() {
        return apiClient.get(`${BASE}/connections`);
    },
    deleteProvider(id) {
        return apiClient.delete(`${BASE}/providers/${id}`);
    },
    disconnect(id) {
        return apiClient.delete(`${BASE}/connections/${id}`);
    },
};
