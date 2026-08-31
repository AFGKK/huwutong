import client from './client';

export default     {
        // Subscriptions
        subscriptions(params = {}) {
        return client.get('/portal/billing/subscriptions', { params });
    },
    list(params = {}) {
        return client.get('/billing/subscriptions', { params });
    },
    create(data) {
        return client.post('/billing/subscriptions', data);
    },
    selfSubscribe(data) {
        return client.post('/portal/billing/self-subscribe', data);
    },
    show(id) {
        return client.get(`/billing/subscriptions/${id}`);
    },
    changePlan(id, data) {
        return client.put(`/billing/subscriptions/${id}/plan`, data);
    },
    cancel(id, reason) {
        return client.post(`/billing/subscriptions/${id}/cancel`, { reason });
    },
    resume(id) {
        return client.post(`/billing/subscriptions/${id}/resume`);
    },
    renew(id) {
        return client.post(`/billing/subscriptions/${id}/renew`);
    },
    suspend(id) {
        return client.post(`/billing/subscriptions/${id}/suspend`);
    },

    // Invoices
    invoices(params = {}) {
        return client.get('/portal/billing/invoices', { params });
    },
    showInvoice(id) {
        return client.get(`/portal/billing/invoices/${id}`);
    },
    markPaid(id, transactionId) {
        return client.post(`/billing/invoices/${id}/mark-paid`, { transaction_id: transactionId });
    },
    payInvoice(id, paymentMethod = 'gateway') {
        return client.post(`/portal/billing/invoices/${id}/pay`, { payment_method: paymentMethod });
    },
    paymentStatus(id) {
        return client.get(`/portal/billing/invoices/${id}/payment-status`);
    },

    // Stats
    stats() {
        return client.get('/billing/stats');
    },
    invoiceStats() {
        return client.get('/billing/invoice-stats');
    },

    // Pricing Plans
    getPublicPlans() {
        return client.get('/billing/plans/public');
    },
    getPlans(params = {}) {
        return client.get('/billing/plans', { params });
    },
    createPlan(data) {
        return client.post('/billing/plans', data);
    },
    updatePlan(id, data) {
        return client.put(`/billing/plans/${id}`, data);
    },
    deletePlan(id) {
        return client.delete(`/billing/plans/${id}`);
    },

    // Coupons
    getCoupons(params = {}) {
        return client.get('/billing/coupons', { params });
    },
    createCoupon(data) {
        return client.post('/billing/coupons', data);
    },
    updateCoupon(id, data) {
        return client.put(`/billing/coupons/${id}`, data);
    },
    validateCoupon(data) {
        return client.post('/billing/coupons/validate', data);
    },
    getCouponStats() {
        return client.get('/billing/coupons/stats');
    },
    getCouponRedemptions(id, params = {}) {
        return client.get(`/billing/coupons/${id}/redemptions`, { params });
    },
};
