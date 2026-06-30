import client from './client';

/**
 * M2-131 发票/账单历史完整查询 API
 */
export default {
    /**
     * 获取账单列表
     */
    invoices(params = {}) {
        return client.get('/billing/invoices', { params });
    },

    /**
     * 获取账单详情
     */
    invoiceDetail(id) {
        return client.get(`/billing/invoices/${id}`);
    },

    /**
     * 获取账单统计
     */
    stats() {
        return client.get('/billing/stats');
    },

    /**
     * 获取订阅列表（筛选用）
     */
    subscriptions() {
        return client.get('/billing/subscriptions');
    },

    /**
     * 获取支付失败记录
     */
    failedPayments() {
        return client.get('/billing/failed-payments');
    },

    /**
     * 获取自动续费扣款记录
     */
    autoRenewals() {
        return client.get('/billing/auto-renewals');
    },

    /**
     * 获取筛选选项
     */
    filterOptions() {
        return client.get('/billing/filter-options');
    },
};
