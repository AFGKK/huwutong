import apiClient from './client';

export function getComplianceReports(params = {}) {
    return apiClient.get('/admin/license/compliance-reports', { params });
}

export function getComplianceReport(id) {
    return apiClient.get(`/admin/license/compliance-reports/${id}`);
}

export function createComplianceReport(data) {
    return apiClient.post('/license/compliance-reports', data);
}

export function deleteComplianceReport(id) {
    return apiClient.delete(`/admin/license/compliance-reports/${id}`);
}

export function getComplianceReportStats() {
    return apiClient.get('/license/compliance-reports/stats');
}

export function getMyComplianceReports(params = {}) {
    return apiClient.get('/license/compliance-reports/my', { params });
}

export function getComplianceReportDownloadUrl(id) {
    return `/license/compliance-reports/${id}/download`;
}
