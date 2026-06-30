import client from './client';

export default {
    // ── 促销活动 ──
    list(params = {}) { return client.get('/promotions', { params }); },
    show(id) { return client.get(`/promotions/${id}`); },
    create(data) { return client.post('/promotions', data); },
    update(id, data) { return client.put(`/promotions/${id}`, data); },
    publish(id) { return client.post(`/promotions/${id}/publish`); },
    pause(id) { return client.post(`/promotions/${id}/pause`); },
    stats() { return client.get('/promotions/stats'); },

    // ── 企业年框合同 ──
    listContracts(params = {}) { return client.get('/contracts', { params }); },
    showContract(id) { return client.get(`/contracts/${id}`); },
    createContract(data) { return client.post('/contracts', data); },
    updateContract(id, data) { return client.put(`/contracts/${id}`, data); },
    approveContract(id, data) { return client.post(`/contracts/${id}/approve`, data); },
    contractStats() { return client.get('/contracts/stats'); },

    // ── 优惠券 ──
    listCoupons(params = {}) { return client.get('/coupons', { params }); },
    createCoupon(data) { return client.post('/coupons', data); },

    // ── 客户门户 ──
    activePromotions() { return client.get('/portal/promotions/active'); },
    customerCoupons() { return client.get('/portal/promotions/coupons'); },
};
