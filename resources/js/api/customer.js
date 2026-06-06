import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/customers', { params });
    },
    show(id) {
        return apiClient.get(`/customers/${id}`);
    },
    create(data) {
        return apiClient.post('/customers', data);
    },
    update(id, data) {
        return apiClient.put(`/customers/${id}`, data);
    },
    stats() {
        return apiClient.get('/customers/stats');
    },
    licenses(id, params) {
        return apiClient.get(`/customers/${id}/licenses`, { params });
    },
};
