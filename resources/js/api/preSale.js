import apiClient from '@/api/client';

const BASE = '/admin/pre-sale';

export default {
    // 活动管理
    list(params = {}) {
        return apiClient.get(BASE, { params });
    },
    show(id) {
        return apiClient.get(`${BASE}/${id}`);
    },
    create(data) {
        return apiClient.post(BASE, data);
    },
    update(id, data) {
        return apiClient.put(`${BASE}/${id}`, data);
    },
    publish(id) {
        return apiClient.post(`${BASE}/${id}/publish`);
    },
    cancel(id, reason = '') {
        return apiClient.post(`${BASE}/${id}/cancel`, { reason });
    },
    checkStatus(id) {
        return apiClient.post(`${BASE}/${id}/check-status`);
    },
    complete(id) {
        return apiClient.post(`${BASE}/${id}/complete`);
    },
    destroy(id) {
        return apiClient.delete(`${BASE}/${id}`);
    },
    stats() {
        return apiClient.get(`${BASE}/stats`);
    },

    // 订单管理
    listOrders(params = {}) {
        return apiClient.get(`${BASE}/orders`, { params });
    },
    placeOrder(data) {
        return apiClient.post(`${BASE}/orders`, data);
    },
    payDeposit(orderId, paymentMethod = 'gateway') {
        return apiClient.post(`${BASE}/orders/${orderId}/pay-deposit`, { payment_method: paymentMethod });
    },
    payFinal(orderId, paymentMethod = 'gateway') {
        return apiClient.post(`${BASE}/orders/${orderId}/pay-final`, { payment_method: paymentMethod });
    },
    updateFulfillment(orderId, status) {
        return apiClient.put(`${BASE}/orders/${orderId}/fulfillment`, { status });
    },

    // 活动更新
    getUpdates(campaignId) {
        return apiClient.get(`${BASE}/${campaignId}/updates`);
    },
    postUpdate(campaignId, data) {
        return apiClient.post(`${BASE}/${campaignId}/updates`, data);
    },
    deleteUpdate(updateId) {
        return apiClient.delete(`${BASE}/updates/${updateId}`);
    },
};
