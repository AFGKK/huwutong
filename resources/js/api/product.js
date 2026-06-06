import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/products', { params });
    },
    show(id) {
        return apiClient.get(`/products/${id}`);
    },
    create(data) {
        return apiClient.post('/products', data);
    },
    update(id, data) {
        return apiClient.put(`/products/${id}`, data);
    },
    stats() {
        return apiClient.get('/products/stats');
    },
    features(id) {
        return apiClient.get(`/products/${id}/features`);
    },
    assignFeatures(id, featureIds) {
        return apiClient.post(`/products/${id}/features`, { feature_ids: featureIds });
    },
    licenses(id, params) {
        return apiClient.get(`/products/${id}/licenses`, { params });
    },
};
