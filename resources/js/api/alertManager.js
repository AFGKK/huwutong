import apiClient from './client';

const alertManagerApi = {
    dashboard() { return apiClient.get('/alert-manager/dashboard'); },
    aggregate() { return apiClient.post('/alert-manager/aggregate'); },
    aggregationGroups(params) { return apiClient.get('/alert-manager/aggregation-groups', { params }); },
    aggregationDetail(groupKey) { return apiClient.get(`/alert-manager/aggregation-groups/${groupKey}`); },

    listSilenceRules(params) { return apiClient.get('/alert-manager/silence-rules', { params }); },
    storeSilenceRule(data) { return apiClient.post('/alert-manager/silence-rules', data); },
    updateSilenceRule(id, data) { return apiClient.put(`/alert-manager/silence-rules/${id}`, data); },
    deleteSilenceRule(id) { return apiClient.delete(`/alert-manager/silence-rules/${id}`); },
    toggleSilenceRule(id) { return apiClient.post(`/alert-manager/silence-rules/${id}/toggle`); },

    checkFatigue(ruleId) { return apiClient.get(`/alert-manager/fatigue/${ruleId}`); },
    autoDowngrade() { return apiClient.post('/alert-manager/auto-downgrade'); },
    listFatigueSettings() { return apiClient.get('/alert-manager/fatigue-settings'); },
    storeFatigueSetting(data) { return apiClient.post('/alert-manager/fatigue-settings', data); },
    updateFatigueSetting(id, data) { return apiClient.put(`/alert-manager/fatigue-settings/${id}`, data); },
    deleteFatigueSetting(id) { return apiClient.delete(`/alert-manager/fatigue-settings/${id}`); },

    generateDigest() { return apiClient.get('/alert-manager/digest'); },
    noiseAnalysis(params) { return apiClient.get('/alert-manager/noise-analysis', { params }); },
    notificationStats(params) { return apiClient.get('/alert-manager/notification-stats', { params }); },
};

export default alertManagerApi;
