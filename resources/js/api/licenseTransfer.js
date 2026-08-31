import apiClient from './client';

export default {
    getList(params) {
        return apiClient.get('/ownership-transfer', { params });
    },
    getStats() {
        return apiClient.get('/ownership-transfer/stats');
    },
    createRequest(data) {
        return apiClient.post('/ownership-transfer', data);
    },
    getDetail(id) {
        return apiClient.get(`/ownership-transfer/${id}`);
    },
    approve(id, data) {
        return apiClient.post(`/ownership-transfer/${id}/approve`, data);
    },
    reject(id, data) {
        return apiClient.post(`/ownership-transfer/${id}/reject`, data);
    },
    cancel(id) {
        return apiClient.post(`/ownership-transfer/${id}/cancel`);
    },
    generateCode(id) {
        return apiClient.post(`/ownership-transfer/${id}/generate-code`);
    },
    verifyCode(id, code) {
        return apiClient.post(`/ownership-transfer/${id}/verify-code`, { code });
    },
};
