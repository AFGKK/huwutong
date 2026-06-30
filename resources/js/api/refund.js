import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/refunds', { params });
    },
    show(id) {
        return apiClient.get(`/refunds/${id}`);
    },
    create(data) {
        return apiClient.post('/refunds', data);
    },
    stats() {
        return apiClient.get('/refunds/stats');
    },

    // 风控引擎
    storeWithRisk(data) {
        return apiClient.post('/refunds/with-risk', data);
    },
    assessRisk(id) {
        return apiClient.post(`/refunds/${id}/assess-risk`);
    },
    executeDecision(id) {
        return apiClient.post(`/refunds/${id}/execute-decision`);
    },
    reviewRefund(id, action, note) {
        return apiClient.post(`/refunds/${id}/review`, { action, note });
    },
    riskStats() {
        return apiClient.get('/refund-risk/stats');
    },
    riskRules() {
        return apiClient.get('/refund-risk/rules');
    },
    updateRiskRule(id, data) {
        return apiClient.put(`/refund-risk/rules/${id}`, data);
    },
};
