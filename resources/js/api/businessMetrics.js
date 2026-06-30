import apiClient from './client';

const businessMetricsApi = {
    dashboard() {
        return apiClient.get('/admin/business-metrics/dashboard');
    },
    overview() {
        return apiClient.get('/admin/business-metrics/overview');
    },
    mrrTrend(params = {}) {
        return apiClient.get('/admin/business-metrics/mrr-trend', { params });
    },
    metricTrends(params = {}) {
        return apiClient.get('/admin/business-metrics/metric-trends', { params });
    },
    churnTrend(params = {}) {
        return apiClient.get('/admin/business-metrics/churn-trend', { params });
    },
    cohortAnalysis() {
        return apiClient.get('/admin/business-metrics/cohort-analysis');
    },
    exportData(params = {}) {
        return apiClient.get('/admin/business-metrics/export', { params });
    },
};

export default businessMetricsApi;
