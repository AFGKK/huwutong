import apiClient from './client';

export default {
    health() {
        return apiClient.get('/hsm/health');
    },
    stats() {
        return apiClient.get('/hsm/stats');
    },
    keys() {
        return apiClient.get('/hsm/keys');
    },
    init(data) {
        return apiClient.post('/hsm/init', data);
    },
    rotate(data = {}) {
        return apiClient.post('/hsm/rotate', data);
    },
    sign(data) {
        return apiClient.post('/hsm/sign', data);
    },
};
