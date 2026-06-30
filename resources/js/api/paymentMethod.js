import client from './client';

export default {
    // 管理端
    adminIndex(params = {}) {
        return client.get('/admin/payment-methods', { params });
    },
    adminDashboard() {
        return client.get('/admin/payment-methods/dashboard');
    },
    adminShow(id) {
        return client.get(`/admin/payment-methods/${id}`);
    },
    adminDelete(id) {
        return client.delete(`/admin/payment-methods/${id}`);
    },
    adminForceDelete(id) {
        return client.delete(`/admin/payment-methods/${id}/force`);
    },

    // 客户门户端
    portalIndex() {
        return client.get('/payment-methods');
    },
    portalStore(data) {
        return client.post('/payment-methods', data);
    },
    portalSetDefault(id) {
        return client.post(`/payment-methods/${id}/default`);
    },
    portalDelete(id) {
        return client.delete(`/payment-methods/${id}`);
    },
};
