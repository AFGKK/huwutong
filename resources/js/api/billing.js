import apiClient from './client';

const billingApi = {
    // Subscription
    subscriptions(params) {
        return apiClient.get('/billing/subscriptions', { params });
    },
    createSubscription(data) {
        return apiClient.post('/billing/subscriptions', data);
    },
    getSubscription(id) {
        return apiClient.get(`/billing/subscriptions/${id}`);
    },
    changePlan(id, data) {
        return apiClient.put(`/billing/subscriptions/${id}/plan`, data);
    },
    cancelSubscription(id, data) {
        return apiClient.post(`/billing/subscriptions/${id}/cancel`, data);
    },
    resumeSubscription(id) {
        return apiClient.post(`/billing/subscriptions/${id}/resume`);
    },
    manualRenew(id) {
        return apiClient.post(`/billing/subscriptions/${id}/renew`);
    },

    // Invoices
    invoices(params) {
        return apiClient.get('/billing/invoices', { params });
    },
    getInvoice(id) {
        return apiClient.get(`/billing/invoices/${id}`);
    },
    markInvoicePaid(id, transactionId) {
        return apiClient.post(`/billing/invoices/${id}/mark-paid`, { transaction_id: transactionId });
    },

    // Stats
    stats() {
        return apiClient.get('/billing/stats');
    },
    invoiceStats() {
        return apiClient.get('/billing/invoice-stats');
    },
};

export default billingApi;
