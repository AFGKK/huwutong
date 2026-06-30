import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/license-marketplace/dashboard');
    },

    // 挂牌管理
    listListings(params = {}) {
        return client.get('/admin/license-marketplace/listings', { params });
    },
    createListing(data) {
        return client.post('/admin/license-marketplace/listings', data);
    },
    approveListing(id, reviewNotes = null) {
        return client.post(`/admin/license-marketplace/listings/${id}/approve`, { review_notes: reviewNotes });
    },
    rejectListing(id, reason) {
        return client.post(`/admin/license-marketplace/listings/${id}/reject`, { reason });
    },
    cancelListing(id) {
        return client.post(`/admin/license-marketplace/listings/${id}/cancel`);
    },
    purchaseListing(listingId, buyerCustomerId) {
        return client.post(`/admin/license-marketplace/listings/${listingId}/purchase`, { buyer_customer_id: buyerCustomerId });
    },

    // 交易
    listTransactions(params = {}) {
        return client.get('/admin/license-marketplace/transactions', { params });
    },

    // 纠纷
    listDisputes(params = {}) {
        return client.get('/admin/license-marketplace/disputes', { params });
    },
    resolveDispute(disputeId, data) {
        return client.post(`/admin/license-marketplace/disputes/${disputeId}/resolve`, data);
    },

    // 卖家评分
    getSellerScore(customerId) {
        return client.get(`/admin/license-marketplace/seller-score/${customerId}`);
    },
};
