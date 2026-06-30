import apiClient from './client';

const apiImpactApi = {
    dashboard() { return apiClient.get('/admin/api-impact/dashboard'); },
    overallReport(params) { return apiClient.get('/admin/api-impact/overall-report', { params }); },
    analyzeVersion(versionId, params) { return apiClient.get(`/admin/api-impact/analyze/${versionId}`, { params }); },
    customerUsage(tenantId, params) { return apiClient.get(`/admin/api-impact/customer-usage/${tenantId}`, { params }); },
    sendNotifications(versionId, params) { return apiClient.post(`/admin/api-impact/notify/${versionId}`, params); },
    notificationHistory(versionId) { return apiClient.get(`/admin/api-impact/notifications/${versionId}`); },
    exportReport(versionId, params) { return apiClient.get(`/admin/api-impact/export/${versionId}`, { params }); },
};

export default apiImpactApi;
