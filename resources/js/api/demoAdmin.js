import apiClient from './client';

const BASE = '/admin/demo';

export default {
    getAnalytics() {
        return apiClient.get(`${BASE}/analytics`).then(r => r.data);
    },
    getConfig() {
        return apiClient.get(`${BASE}/config`).then(r => r.data);
    },
    updateConfig(data) {
        return apiClient.put(`${BASE}/config`, data).then(r => r.data);
    },
    getEmbedCode() {
        return apiClient.get(`${BASE}/embed-code`).then(r => r.data);
    },
    getSessions(params) {
        return apiClient.get(`${BASE}/sessions`, { params }).then(r => r.data);
    },
};
