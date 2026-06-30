import apiClient from './client';

export default {
    getDashboard(params) {
        return apiClient.get('/product-analytics/dashboard', { params });
    },
    getProductRanking() {
        return apiClient.get('/product-analytics/product-ranking');
    },
    getModuleUsage() {
        return apiClient.get('/product-analytics/module-usage');
    },
    getRegionalGrowth(params) {
        return apiClient.get('/product-analytics/regional-growth', { params });
    },
    getLicenseTrend(params) {
        return apiClient.get('/product-analytics/license-trend', { params });
    },
    getActivationTrend(params) {
        return apiClient.get('/product-analytics/activation-trend', { params });
    },
    getHeatmap(params) {
        return apiClient.get('/product-analytics/heatmap', { params });
    },
    getProductMonthlyTrend(params) {
        return apiClient.get('/product-analytics/product-monthly-trend', { params });
    },
    getRegionalTrend(params) {
        return apiClient.get('/product-analytics/regional-trend', { params });
    },
    getSummary(params) {
        return apiClient.get('/product-analytics/summary', { params });
    },
};
