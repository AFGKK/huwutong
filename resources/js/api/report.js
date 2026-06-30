import client from './client';

export default {
    dashboard() {
        return client.get('/reports/dashboard');
    },
    revenueTrend(params = {}) {
        return client.get('/reports/revenue-trend', { params });
    },
    mrrTrend(params = {}) {
        return client.get('/reports/mrr-trend', { params });
    },
    subscriptionAnalytics() {
        return client.get('/reports/subscription-analytics');
    },
    planDistribution() {
        return client.get('/reports/plan-distribution');
    },
    customerLtv() {
        return client.get('/reports/customer-ltv');
    },
    churnAnalysis() {
        return client.get('/reports/churn-analysis');
    },
};
