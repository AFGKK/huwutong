import apiClient from './client';

export default {
    // 客户侧
    myBalance(params = {}) {
        return apiClient.get('/portal/prepaid/balance', { params });
    },
    recharge(data) {
        return apiClient.post('/portal/prepaid/recharge', data);
    },
    myTransactions(params = {}) {
        return apiClient.get('/portal/prepaid/transactions', { params });
    },
    saveAutoRecharge(data) {
        return apiClient.post('/portal/prepaid/auto-recharge', data);
    },
    checkAutoRecharge() {
        return apiClient.post('/portal/prepaid/check-auto-recharge');
    },

    // 管理侧 - 指定客户
    customerBalance(customerId) {
        return apiClient.get(`/billing/prepaid/customers/${customerId}/balance`);
    },
    adminRecharge(customerId, data) {
        return apiClient.post(`/billing/prepaid/customers/${customerId}/recharge`, data);
    },
    adminDeduct(customerId, data) {
        return apiClient.post(`/billing/prepaid/customers/${customerId}/deduct`, data);
    },
    adminAdjust(customerId, data) {
        return apiClient.post(`/billing/prepaid/customers/${customerId}/adjust`, data);
    },
    adminTransactions(customerId, params = {}) {
        return apiClient.get(`/billing/prepaid/customers/${customerId}/transactions`, { params });
    },
    setCreditLimit(customerId, data) {
        return apiClient.post(`/billing/prepaid/customers/${customerId}/credit-limit`, data);
    },
    getCreditLimit(customerId) {
        return apiClient.get(`/billing/prepaid/customers/${customerId}/credit-limit`);
    },

    // 管理侧 - 全局
    getStats() {
        return apiClient.get('/billing/prepaid/stats');
    },
    allTransactions(params = {}) {
        return apiClient.get('/billing/prepaid/all-transactions', { params });
    },
};
