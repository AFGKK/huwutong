import apiClient from './client';

export default {
    // 保留策略
    list() {
        return apiClient.get('/admin/audit-retention-policies');
    },
    overview() {
        return apiClient.get('/admin/audit-retention-policies/overview');
    },
    create(data) {
        return apiClient.post('/admin/audit-retention-policies', data);
    },
    update(id, data) {
        return apiClient.put(`/admin/audit-retention-policies/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/admin/audit-retention-policies/${id}`);
    },
    previewPrune(data) {
        return apiClient.post('/admin/audit-retention-policies/preview-prune', data);
    },
};
