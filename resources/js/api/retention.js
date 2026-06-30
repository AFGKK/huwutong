import apiClient from './client';

export default {
    failureStats() {
        return apiClient.get('/retention/failure-stats');
    },
    subscriptionFailures(subscriptionId, params) {
        return apiClient.get(`/retention/subscriptions/${subscriptionId}/failures`, { params });
    },
    manualRetry(subscriptionId, data) {
        return apiClient.post(`/retention/subscriptions/${subscriptionId}/manual-retry`, data);
    },
    pendingEscalations(params) {
        return apiClient.get('/retention/escalations', { params });
    },
    resolveEscalation(escalationId, data) {
        return apiClient.post(`/retention/escalations/${escalationId}/resolve`, data);
    },

    // Renewal configs
    getConfigs() {
        return apiClient.get('/retention/configs');
    },
    getConfig(id) {
        return apiClient.get(`/retention/configs/${id}`);
    },
    saveConfig(data, id = null) {
        if (id) {
            return apiClient.put(`/retention/configs/${id}`, data);
        }
        return apiClient.post('/retention/configs', data);
    },
    toggleConfig(id) {
        return apiClient.post(`/retention/configs/${id}/toggle`);
    },
    deleteConfig(id) {
        return apiClient.delete(`/retention/configs/${id}`);
    },
};
