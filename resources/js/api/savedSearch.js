import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/saved-searches', { params });
    },
    create(data) {
        return apiClient.post('/saved-searches', data);
    },
    update(id, data) {
        return apiClient.put(`/saved-searches/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/saved-searches/${id}`);
    },
};
