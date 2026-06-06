import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/webhook-endpoints', { params });
    },
    create(data) {
        return apiClient.post('/webhook-endpoints', data);
    },
    show(id) {
        return apiClient.get(`/webhook-endpoints/${id}`);
    },
    update(id, data) {
        return apiClient.put(`/webhook-endpoints/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/webhook-endpoints/${id}`);
    },
    togglePause(id) {
        return apiClient.post(`/webhook-endpoints/${id}/toggle-pause`);
    },
    test(id) {
        return apiClient.post(`/webhook-endpoints/${id}/test`);
    },
    eventTypes() {
        return apiClient.get('/webhook-endpoints/event-types');
    },
};
