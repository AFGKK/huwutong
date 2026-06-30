import apiClient from './client';

export default {
    // ─── 合规框架 ───
    frameworks() {
        return apiClient.get('/admin/compliance/frameworks').then(r => r.data);
    },
    seedFrameworks() {
        return apiClient.post('/admin/compliance/frameworks/seed').then(r => r.data);
    },

    // ─── 合规报告 ───
    reports(params) {
        return apiClient.get('/admin/compliance/reports', { params }).then(r => r.data);
    },
    generateReport(data) {
        return apiClient.post('/admin/compliance/reports', data).then(r => r.data);
    },
    showReport(id) {
        return apiClient.get(`/admin/compliance/reports/${id}`).then(r => r.data);
    },
    deleteReport(id) {
        return apiClient.delete(`/admin/compliance/reports/${id}`).then(r => r.data);
    },

    // ─── 报告导出 ───
    reportExports(reportId) {
        return apiClient.get(`/admin/compliance/reports/${reportId}/exports`).then(r => r.data);
    },
    exportReport(reportId, format) {
        return apiClient.post(`/admin/compliance/reports/${reportId}/export`, { format }).then(r => r.data);
    },

    // ─── 治理概览 ───
    governanceDashboard() {
        return apiClient.get('/admin/audit-governance/dashboard').then(r => r.data);
    },
};
