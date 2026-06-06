import apiClient from './client';

export default {
    create() {
        return apiClient.post('/sandbox/create');
    },
    status() {
        return apiClient.get('/sandbox/status');
    },
    reset() {
        return apiClient.post('/sandbox/reset');
    },
    licenses() {
        return apiClient.get('/sandbox/licenses');
    },
};
