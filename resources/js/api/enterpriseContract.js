import apiClient from './client';

export default {
    dashboard() {
        return apiClient.get('/admin/enterprise-contracts/dashboard');
    },
    expiring(days = 30) {
        return apiClient.get('/admin/enterprise-contracts/expiring', { params: { within_days: days } });
    },
    index(params = {}) {
        return apiClient.get('/admin/enterprise-contracts', { params });
    },
    show(id) {
        return apiClient.get(`/admin/enterprise-contracts/${id}`);
    },
    store(data) {
        return apiClient.post('/admin/enterprise-contracts', data);
    },
    update(id, data) {
        return apiClient.put(`/admin/enterprise-contracts/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/admin/enterprise-contracts/${id}`);
    },
    submitForApproval(id) {
        return apiClient.post(`/admin/enterprise-contracts/${id}/submit`);
    },
    approve(id, action, notes = '') {
        return apiClient.post(`/admin/enterprise-contracts/${id}/approve`, { action, notes });
    },
    terminate(id) {
        return apiClient.post(`/admin/enterprise-contracts/${id}/terminate`);
    },
    renew(id, data = {}) {
        return apiClient.post(`/admin/enterprise-contracts/${id}/renew`, data);
    },
};
