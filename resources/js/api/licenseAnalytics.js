import apiClient from './client';

export default {
    // ── 视图使用的方法 ──
    dashboard() {
        return apiClient.get('/license-analytics/dashboard');
    },
    geoDistribution() {
        return apiClient.get('/license-analytics/geo-distribution');
    },
    activationTrend(params) {
        return apiClient.get('/license-analytics/activation-trend', { params });
    },
    violationTrend(params) {
        return apiClient.get('/license-analytics/violation-trend', { params });
    },
    sdkStats() {
        return apiClient.get('/license-analytics/sdk-stats');
    },
    productStats() {
        return apiClient.get('/license-analytics/product-stats');
    },
    violationTypes() {
        return apiClient.get('/license-analytics/violation-types');
    },
    violations(params) {
        return apiClient.get('/license-analytics/violations', { params });
    },
    detectViolations() {
        return apiClient.post('/license-analytics/detect-violations');
    },
    backfill() {
        return apiClient.post('/license-analytics/backfill');
    },
    geoDetail(countryCode) {
        return apiClient.get(`/license-analytics/geo/${countryCode}`);
    },

    // ── 旧方法名（兼容其他调用方） ──
    getDashboard() {
        return this.dashboard();
    },
    getSummary() {
        return apiClient.get('/license-analytics/summary');
    },
    getTypeDistribution() {
        return apiClient.get('/license-analytics/type-distribution');
    },
    getStatusDistribution() {
        return apiClient.get('/license-analytics/status-distribution');
    },
    getPlatformDistribution() {
        return apiClient.get('/license-analytics/platform-distribution');
    },
    getActivationTrend(params) {
        return this.activationTrend(params);
    },
    getCreationTrend(params) {
        return apiClient.get('/license-analytics/creation-trend', { params });
    },
    getGeoDistribution() {
        return this.geoDistribution();
    },
    getUtilization() {
        return apiClient.get('/license-analytics/utilization');
    },
    getSdkStats() {
        return this.sdkStats();
    },
    getProductStats() {
        return this.productStats();
    },
    getViolations(params) {
        return this.violations(params);
    },
    getHeatmap(params) {
        return apiClient.get('/license-analytics/heatmap', { params });
    },
};
