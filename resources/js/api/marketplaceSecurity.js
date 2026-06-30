import apiClient from './client';

export default {
    stats() {
        return apiClient.get('/marketplace/security/stats');
    },
    scanApp(id) {
        return apiClient.get(`/marketplace/security/apps/${id}`);
    },
    scanReview(id) {
        return apiClient.get(`/marketplace/security/reviews/${id}`);
    },
    scanAllApps() {
        return apiClient.post('/marketplace/security/scan-apps');
    },
    scanAllReviews() {
        return apiClient.post('/marketplace/security/scan-reviews');
    },
};
