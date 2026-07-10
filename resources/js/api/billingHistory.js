import client from './client';

/**
 * M2-131 发票/账单历史完整查询 API（客户门户）
 */
const BASE = '/portal/billing';

export default {
    invoices(params = {}) {
        return client.get(`${BASE}/invoices`, { params });
    },

    invoiceDetail(id) {
        return client.get(`${BASE}/invoices/${id}`);
    },

    stats() {
        return client.get(`${BASE}/stats`);
    },

    subscriptions() {
        return client.get(`${BASE}/subscriptions`);
    },

    failedPayments() {
        return client.get(`${BASE}/failed-payments`);
    },

    autoRenewals() {
        return client.get(`${BASE}/auto-renewals`);
    },

    filterOptions() {
        return client.get(`${BASE}/filter-options`);
    },
};
