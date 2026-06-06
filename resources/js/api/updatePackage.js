import apiClient from './client';

const updatePackageApi = {
    list(productId, params) {
        return apiClient.get(`/products/${productId}/updates`, { params });
    },
    show(id) {
        return apiClient.get(`/updates/${id}`);
    },
    create(productId, data) {
        return apiClient.post(`/products/${productId}/updates`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    publish(id) {
        return apiClient.post(`/updates/${id}/publish`);
    },
    deprecate(id) {
        return apiClient.post(`/updates/${id}/deprecate`);
    },
    destroy(id) {
        return apiClient.delete(`/updates/${id}`);
    },
    stats(id) {
        return apiClient.get(`/updates/${id}/stats`);
    },
    checkUpdate(productId, currentVersion) {
        return apiClient.get(`/products/${productId}/updates/check`, {
            params: { current_version: currentVersion },
        });
    },
};

export default updatePackageApi;
