import apiClient from './client';

const apiKeyApi = {
    list(params) {
        return apiClient.get('/api-keys', { params });
    },
    show(id) {
        return apiClient.get(`/api-keys/${id}`);
    },
    create(data) {
        return apiClient.post('/api-keys', data);
    },
    update(id, data) {
        return apiClient.put(`/api-keys/${id}`, data);
    },
    delete(id) {
        return apiClient.delete(`/api-keys/${id}`);
    },
    regenerate(id) {
        return apiClient.post(`/api-keys/${id}/regenerate`);
    },
};

export default apiKeyApi;
