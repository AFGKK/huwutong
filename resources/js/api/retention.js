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
};
