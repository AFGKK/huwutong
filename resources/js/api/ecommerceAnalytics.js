import apiClient from './client';

export default {
    getDashboard(params) {
        return apiClient.get('/ecommerce-analytics/dashboard', { params });
    },
    getSummary(params) {
        return apiClient.get('/ecommerce-analytics/summary', { params });
    },
    getSalesTrend(params) {
        return apiClient.get('/ecommerce-analytics/sales-trend', { params });
    },
    getProductRanking(params) {
        return apiClient.get('/ecommerce-analytics/product-ranking', { params });
    },
    getRepurchaseRate(params) {
        return apiClient.get('/ecommerce-analytics/repurchase-rate', { params });
    },
    getPaymentChannels(params) {
        return apiClient.get('/ecommerce-analytics/payment-channels', { params });
    },
    getCustomerMetrics(params) {
        return apiClient.get('/ecommerce-analytics/customer-metrics', { params });
    },
    getComparison(params) {
        return apiClient.get('/ecommerce-analytics/comparison', { params });
    },
    getForecast(params) {
        return apiClient.get('/ecommerce-analytics/forecast', { params });
    },
    getExportCsvUrl(params) {
        return apiClient.getUri({ url: '/ecommerce-analytics/export-csv', params });
    },
};
