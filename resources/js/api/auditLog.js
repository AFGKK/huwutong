import apiClient from './client';

const auditLogApi = {
    list(params) {
        return apiClient.get('/audit-logs', { params });
    },
    show(id) {
        return apiClient.get(`/audit-logs/${id}`);
    },
    stats() {
        return apiClient.get('/audit-logs/stats');
    },
};

export default auditLogApi;
