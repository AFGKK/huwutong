import apiClient from './client';

export default {
    // ─── 挂牌管理 ───
    createListing(data) {
        return apiClient.post('/admin/resale/listings', data);
    },
    updateListing(id, data) {
        return apiClient.put(`/admin/resale/listings/${id}`, data);
    },
    publishListing(id) {
        return apiClient.post(`/admin/resale/listings/${id}/publish`);
    },
    reviewListing(id, data) {
        return apiClient.post(`/admin/resale/listings/${id}/review`, data);
    },
    cancelListing(id) {
        return apiClient.post(`/admin/resale/listings/${id}/cancel`);
    },
    getListingDetail(id) {
        return apiClient.get(`/admin/resale/listings/${id}`);
    },
    getSellableLicenses() {
        return apiClient.get('/admin/resale/listings/sellable');
    },
    getMyListings(params = {}) {
        return apiClient.get('/admin/resale/listings/mine', { params });
    },

    // ─── 市场浏览 ───
    browseMarketplace(params = {}) {
        return apiClient.get('/admin/resale/marketplace', { params });
    },
    getMarketStats() {
        return apiClient.get('/admin/resale/stats');
    },
    getSellerStats() {
        return apiClient.get('/admin/resale/stats/seller');
    },

    // ─── 交易管理 ───
    purchaseListing(listingId, data = {}) {
        return apiClient.post(`/admin/resale/listings/${listingId}/purchase`, data);
    },
    confirmPayment(id, data) {
        return apiClient.post(`/admin/resale/transactions/${id}/confirm-payment`, data);
    },
    sellerConfirm(id) {
        return apiClient.post(`/admin/resale/transactions/${id}/seller-confirm`);
    },
    executeTransfer(id) {
        return apiClient.post(`/admin/resale/transactions/${id}/execute-transfer`);
    },
    cancelTransaction(id) {
        return apiClient.post(`/admin/resale/transactions/${id}/cancel`);
    },
};
