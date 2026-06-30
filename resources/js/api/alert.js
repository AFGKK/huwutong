import apiClient from './client';

export default {
    dashboard() {
        return apiClient.get('/alerts/dashboard');
    },
    meta() {
        return apiClient.get('/alerts/meta');
    },
    rules(params = {}) {
        return apiClient.get('/alerts/rules', { params });
    },
    storeRule(data) {
        return apiClient.post('/alerts/rules', data);
    },
    updateRule(id, data) {
        return apiClient.put(`/alerts/rules/${id}`, data);
    },
    destroyRule(id) {
        return apiClient.delete(`/alerts/rules/${id}`);
    },
    events(params = {}) {
        return apiClient.get('/alerts/events', { params });
    },
    showEvent(id) {
        return apiClient.get(`/alerts/events/${id}`);
    },
    acknowledgeEvent(id) {
        return apiClient.post(`/alerts/events/${id}/acknowledge`);
    },
    resolveEvent(id) {
        return apiClient.post(`/alerts/events/${id}/resolve`);
    },
    fire(data) {
        return apiClient.post('/alerts/fire', data);
    },
    evaluate() {
        return apiClient.post('/alerts/evaluate');
    },
    integrations() {
        return apiClient.get('/alerts/integrations');
    },
    storeIntegration(data) {
        return apiClient.post('/alerts/integrations', data);
    },
    updateIntegration(id, data) {
        return apiClient.put(`/alerts/integrations/${id}`, data);
    },
    destroyIntegration(id) {
        return apiClient.delete(`/alerts/integrations/${id}`);
    },
    testIntegration(id) {
        return apiClient.post(`/alerts/integrations/${id}/test`);
    },
};
