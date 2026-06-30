import apiClient from './client';

export default {
    getDashboard() {
        return apiClient.get('/ecommerce-dashboard');
    },
    getToday() {
        return apiClient.get('/ecommerce-dashboard/today');
    },
    getProductRanking(limit = 10) {
        return apiClient.get('/ecommerce-dashboard/product-ranking', { params: { limit } });
    },
    getPaymentSuccessRate() {
        return apiClient.get('/ecommerce-dashboard/payment-success-rate');
    },
    getRefundRate() {
        return apiClient.get('/ecommerce-dashboard/refund-rate');
    },
    getTrend(days = 7) {
        return apiClient.get('/ecommerce-dashboard/trend', { params: { days } });
    },
};
