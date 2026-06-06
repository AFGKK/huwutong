import client from './client';

export default {
    // ─── 货币 & 汇率 ───
    getCurrencies() {
        return client.get('/currencies');
    },
    getRates() {
        return client.get('/currency/rates');
    },
    setRate(data) {
        return client.post('/currency/rates', data);
    },
    deleteRate(id) {
        return client.delete(`/currency/rates/${id}`);
    },
    convert(amount, from, to) {
        return client.post('/currency/convert', { amount, from, to });
    },
    batchConvert(amounts, from, to) {
        return client.post('/currency/batch-convert', { amounts, from, to });
    },
    syncRates(provider = 'ecb') {
        return client.post('/currency/sync-rates', { provider });
    },

    // ─── 定价计划 ───
    getPricingPlans(customerId = null) {
        const params = customerId ? { customer_id: customerId } : {};
        return client.get('/currency/pricing-plans', { params });
    },
    createPricingPlan(data) {
        return client.post('/currency/pricing-plans', data);
    },
    updatePricingPlan(id, data) {
        return client.put(`/currency/pricing-plans/${id}`, data);
    },
    deletePricingPlan(id) {
        return client.delete(`/currency/pricing-plans/${id}`);
    },

    // ─── 客户偏好 ───
    getCustomerPreference(customerId = null) {
        const params = customerId ? { customer_id: customerId } : {};
        return client.get('/currency/customer-preference', { params });
    },
    updateCustomerPreference(data) {
        return client.put('/currency/customer-preference', data);
    },
    getSubscriptionDisplayAmount(subscriptionId) {
        return client.get(`/currency/subscription-display/${subscriptionId}`);
    },
};
