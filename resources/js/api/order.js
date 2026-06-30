import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/orders', { params });
    },
    show(id) {
        return apiClient.get(`/orders/${id}`);
    },
    create(data) {
        return apiClient.post('/orders', data);
    },
    cancel(id, reason) {
        return apiClient.post(`/orders/${id}/cancel`, { reason });
    },
    markPaid(id, data) {
        return apiClient.post(`/orders/${id}/pay`, data);
    },
    skus(params) {
        return apiClient.get('/skus', { params });
    },
    createSku(data) {
        return apiClient.post('/skus', data);
    },
    updateSku(id, data) {
        return apiClient.put(`/skus/${id}`, data);
    },
    deleteSku(id) {
        return apiClient.delete(`/skus/${id}`);
    },
    cart() {
        return apiClient.get('/cart');
    },
    cartSummary() {
        return apiClient.get('/cart/summary');
    },
    addToCart(data) {
        return apiClient.post('/cart/add', data);
    },
    updateCart(data) {
        return apiClient.put('/cart/update', data);
    },
    removeFromCart(skuId) {
        return apiClient.delete('/cart/remove', { data: { sku_id: skuId } });
    },
    clearCart() {
        return apiClient.post('/cart/clear');
    },
    applyCoupon(code) {
        return apiClient.post('/cart/apply-coupon', { code });
    },
    removeCoupon() {
        return apiClient.delete('/cart/coupon');
    },
    mergeCart(sessionId) {
        return apiClient.post('/cart/merge', { session_id: sessionId });
    },
    validateCheckout() {
        return apiClient.post('/cart/validate-checkout');
    },
    checkout() {
        return apiClient.post('/cart/checkout');
    },
};
