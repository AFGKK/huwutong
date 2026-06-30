import apiClient from './client';

export default {
    // ── 分类 ──
    categories() {
        return apiClient.get('/marketplace/categories');
    },
    categoryCreate(data) {
        return apiClient.post('/marketplace/categories', data);
    },
    categoryUpdate(id, data) {
        return apiClient.put(`/marketplace/categories/${id}`, data);
    },
    categoryDelete(id) {
        return apiClient.delete(`/marketplace/categories/${id}`);
    },

    // ── 评价 ──
    reviews(appId, params) {
        return apiClient.get(`/marketplace/apps/${appId}/reviews`, { params });
    },
    reviewStats(appId) {
        return apiClient.get(`/marketplace/apps/${appId}/review-stats`);
    },
    reviewCreate(appId, data) {
        return apiClient.post(`/marketplace/apps/${appId}/reviews`, data);
    },
    reviewUpdate(reviewId, data) {
        return apiClient.put(`/marketplace/reviews/${reviewId}`, data);
    },
    reviewDelete(reviewId) {
        return apiClient.delete(`/marketplace/reviews/${reviewId}`);
    },
    reviewReply(reviewId, data) {
        return apiClient.post(`/marketplace/reviews/${reviewId}/reply`, data);
    },
    reviewModerate(reviewId, action) {
        return apiClient.post(`/marketplace/reviews/${reviewId}/moderate`, { action });
    },

    // ── Banner ──
    banners() {
        return apiClient.get('/marketplace/banners');
    },
    bannersAdmin(params) {
        return apiClient.get('/marketplace/banners/manage', { params });
    },
    bannerCreate(data) {
        return apiClient.post('/marketplace/banners', data);
    },
    bannerUpdate(id, data) {
        return apiClient.put(`/marketplace/banners/${id}`, data);
    },
    bannerDelete(id) {
        return apiClient.delete(`/marketplace/banners/${id}`);
    },

    // ── 统计 ──
    analytics(appId, params) {
        return apiClient.get(`/marketplace/apps/${appId}/analytics`, { params });
    },
};
