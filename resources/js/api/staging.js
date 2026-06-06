import apiClient from './client';

export default {
    index() {
        return apiClient.get('/staging');
    },
    create(data = {}) {
        return apiClient.post('/staging/create', data);
    },
    show(id) {
        return apiClient.get(`/staging/${id}`);
    },
    reset(id) {
        return apiClient.post(`/staging/${id}/reset`);
    },
    update(id, data) {
        return apiClient.put(`/staging/${id}`, data);
    },
    licenses(id) {
        return apiClient.get(`/staging/${id}/licenses`);
    },
};
