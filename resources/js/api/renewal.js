import apiClient from './client';

export default {
    stats() {
        return apiClient.get('/admin/renewal-dashboard/stats');
    },
    expiringLicenses(params) {
        return apiClient.get('/admin/renewal-dashboard/expiring-licenses', { params });
    },
    expiredLicenses(params) {
        return apiClient.get('/admin/renewal-dashboard/expired-licenses', { params });
    },
    batchRenew(data) {
        return apiClient.post('/admin/renewal-dashboard/batch-renew', data);
    },
    renew(id, data) {
        return apiClient.post(`/admin/renewal-dashboard/renew/${id}`, data);
    },
    activityLog() {
        return apiClient.get('/admin/renewal-dashboard/activity-log');
    },
};
