import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/deps-security', { params });
    },
    stats() {
        return apiClient.get('/deps-security/stats');
    },
    updateStatus(id, data) {
        return apiClient.put(`/deps-security/${id}`, data);
    },
    batchUpdate(data) {
        return apiClient.post('/deps-security/batch', data);
    },
    triggerScan() {
        return apiClient.post('/deps-security/scan');
    },
    config() {
        return apiClient.get('/deps-security/config');
    },
};
