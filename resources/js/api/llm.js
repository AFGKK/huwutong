import apiClient from './client';

export default {
    providers() {
        return apiClient.get('/llm/providers');
    },
    createProvider(data) {
        return apiClient.post('/llm/providers', data);
    },
    updateProvider(id, data) {
        return apiClient.put(`/llm/providers/${id}`, data);
    },
    testConnection(id) {
        return apiClient.post(`/llm/providers/${id}/test`);
    },
    chat(messages, options = {}) {
        return apiClient.post('/llm/chat', { messages, ...options });
    },
    chatStream(messages, options = {}) {
        const params = new URLSearchParams({ ...options });
        const baseUrl = apiClient.defaults?.baseURL || '/api';
        return `${baseUrl}/llm/chat-stream?${params}`;
    },
    tokenStats(days = 30) {
        return apiClient.get('/llm/token-stats', { params: { days } });
    },
    logs(params = {}) {
        return apiClient.get('/llm/logs', { params });
    },
    // Health monitoring (production hardening)
    healthStatus() {
        return apiClient.get('/llm/health');
    },
    runHealthCheck() {
        return apiClient.post('/llm/health/check');
    },
    fallbackEvents() {
        return apiClient.get('/llm/fallback-events');
    },
};
