import apiClient from './client';

const BASE = '/admin/pwa';

export default {
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },
    getSubscriptions() {
        return apiClient.get(`${BASE}/subscriptions`).then(r => r.data);
    },
    sendNotification(data) {
        return apiClient.post(`${BASE}/send-notification`, data).then(r => r.data);
    },
    clearCache() {
        return apiClient.post(`${BASE}/clear-cache`).then(r => r.data);
    },
    updateWorker() {
        return apiClient.post(`${BASE}/update-worker`).then(r => r.data);
    },
};
