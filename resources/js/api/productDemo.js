import apiClient from './client';

export default {
    list(productId) {
        return apiClient.get(`/products/${productId}/demos`);
    },
    create(productId, data) {
        return apiClient.post(`/products/${productId}/demos`, data);
    },
    update(demoId, data) {
        return apiClient.put(`/products/demos/${demoId}`, data);
    },
    delete(demoId) {
        return apiClient.delete(`/products/demos/${demoId}`);
    },
    saveSettings(productId, data) {
        return apiClient.post(`/products/${productId}/demos/settings`, data);
    },
    // 前端公开接口
    getPublic(productId) {
        return apiClient.get(`/products/${productId}/demo`);
    },
};
