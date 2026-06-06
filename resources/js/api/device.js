import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/devices', { params });
    },
    show(id) {
        return apiClient.get(`/devices/${id}`);
    },
    deactivate(id, blacklist = false) {
        return apiClient.post(`/devices/${id}/deactivate`, { blacklist });
    },
    stats() {
        return apiClient.get('/devices/stats');
    },
    batch(ids, action) {
        return apiClient.post('/devices/batch', { ids, action });
    },
};
