import apiClient from './client';

export default {
    status() {
        return apiClient.get('/llm/fallback/status');
    },
    reset() {
        return apiClient.post('/llm/fallback/reset');
    },
};
