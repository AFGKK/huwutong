import apiClient from './client';

export default {
    getRules(params) {
        return apiClient.get('/promotion-engine/rules', { params });
    },
    getRule(id) {
        return apiClient.get(`/promotion-engine/rules/${id}`);
    },
    createRule(data) {
        return apiClient.post('/promotion-engine/rules', data);
    },
    updateRule(id, data) {
        return apiClient.put(`/promotion-engine/rules/${id}`, data);
    },
    deleteRule(id) {
        return apiClient.delete(`/promotion-engine/rules/${id}`);
    },
    toggleStatus(id, status) {
        return apiClient.post(`/promotion-engine/rules/${id}/toggle-status`, { status });
    },
    calculateDiscount(data) {
        return apiClient.post('/promotion-engine/calculate', data);
    },
    applyPromotion(data) {
        return apiClient.post('/promotion-engine/apply', data);
    },
    findBestPromotion(data) {
        return apiClient.post('/promotion-engine/best-promotion', data);
    },
    checkStackability(data) {
        return apiClient.post('/promotion-engine/check-stackability', data);
    },
    getStats() {
        return apiClient.get('/promotion-engine/stats');
    },
};
