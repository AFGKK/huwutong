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
    uploadImage(formData) {
        return apiClient.post('/products/upload-image', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    batchAction(action, ids) {
        return apiClient.post('/products/batch-action', { action, ids });
    },
    clone(id) {
        return apiClient.post(`/products/${id}/clone`);
    },
    // SKU
    getSkus(productId) {
        return apiClient.get(`/products/${productId}/skus`);
    },
    createSku(productId, data) {
        return apiClient.post(`/products/${productId}/skus`, data);
    },
    updateSku(id, data) {
        return apiClient.put(`/skus/${id}`, data);
    },
    deleteSku(id) {
        return apiClient.delete(`/skus/${id}`);
    },
    // Specs
    saveSpecs(productId, groups) {
        return apiClient.post(`/products/${productId}/specs`, { groups });
    },
    getSpecs(productId) {
        return apiClient.get(`/products/${productId}/specs`);
    },
    // SEO
    getSeo(productId) {
        return apiClient.get(`/products/${productId}/seo`);
    },
    saveSeo(productId, data) {
        return apiClient.post(`/products/${productId}/seo`, data);
    },
    // Translations
    saveTranslations(productId, translations) {
        return apiClient.post(`/products/${productId}/translations`, { translations });
    },
};
