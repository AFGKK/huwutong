import apiClient from './client';

const apiKeyApi = {
    list(params) {
        return apiClient.get('/api-keys', { params });
    },
    show(id) {
        return apiClient.get(`/api-keys/${id}`);
    },
    create(data) {
        return apiClient.post('/api-keys', data);
    },
    update(id, data) {
        return apiClient.put(`/api-keys/${id}`, data);
    },
    delete(id) {
        return apiClient.delete(`/api-keys/${id}`);
    },
    regenerate(id) {
        return apiClient.post(`/api-keys/${id}/regenerate`);
    },
    toggleActive(id) {
        return apiClient.post(`/api-keys/${id}/toggle`);
    },
    auditLogs(id, params) {
        return apiClient.get(`/api-keys/${id}/audit-logs`, { params });
    },
    usageStats(id) {
        return apiClient.get(`/api-keys/${id}/usage-stats`);
    },
    myOverview() {
        return apiClient.get('/api-keys/stats/overview');
    },
    allAuditLogs(params) {
        return apiClient.get('/api-keys/audit-logs/all', { params });
    },
    getTierConfig() {
        return apiClient.get('/api-keys/config/tiers');
    },
};

export default apiKeyApi;
