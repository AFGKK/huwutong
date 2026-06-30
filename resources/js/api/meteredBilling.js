import apiClient from './client';

export default {
    getOverview() {
        return apiClient.get('/billing/metered/overview');
    },
    getPrices() {
        return apiClient.get('/billing/metered/prices');
    },
    upsertPrice(data) {
        return apiClient.post('/billing/metered/prices', data);
    },
    deletePrice(id) {
        return apiClient.delete(`/billing/metered/prices/${id}`);
    },
    getAvailableMetrics() {
        return apiClient.get('/billing/metered/available-metrics');
    },
    getMeteredSubscriptions(params) {
        return apiClient.get('/billing/metered/subscriptions', { params });
    },
    generateInvoice(subscriptionId, params) {
        return apiClient.post(`/billing/metered/subscriptions/${subscriptionId}/generate-invoice`, params);
    },
    updateSubscriptionConfig(subscriptionId, data) {
        return apiClient.put(`/billing/metered/subscriptions/${subscriptionId}/config`, data);
    },
    batchGenerateInvoices(params) {
        return apiClient.post('/billing/metered/batch-generate-invoices', params);
    },
};
