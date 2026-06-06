import apiClient from './client';

export default {
    status() {
        return apiClient.get('/status');
    },
    history() {
        return apiClient.get('/status/history');
    },
    subscribe(email) {
        return apiClient.post('/status/subscribe', { email });
    },
};
