import apiClient from './client';

export default {
    live() {
        return apiClient.get('/health/live');
    },
    ready() {
        return apiClient.get('/health/ready');
    },
    status() {
        return apiClient.get('/health/status');
    },
};
