import apiClient from './client';

export default {
    getList(params) {
        return apiClient.get('/transfers', { params });
    },
    getStats() {
        return apiClient.get('/transfers/stats');
    },
    createRequest(data) {
        return apiClient.post('/transfers', data);
    },
    getDetail(id) {
        return apiClient.get(`/transfers/${id}`);
    },
    approve(id, data) {
        return apiClient.post(`/transfers/${id}/approve`, data);
    },
    reject(id, data) {
        return apiClient.post(`/transfers/${id}/reject`, data);
    },
    cancel(id) {
        return apiClient.post(`/transfers/${id}/cancel`);
    },
    generateCode(id) {
        return apiClient.post(`/transfers/${id}/generate-code`);
    },
    verifyCode(id, code) {
        return apiClient.post(`/transfers/${id}/verify-code`, { code });
    },
};
