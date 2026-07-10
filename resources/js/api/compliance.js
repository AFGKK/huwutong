import apiClient from './client';

export default {
    // ─── 合规框架（与 audit-governance 后端一致）───
    frameworks() {
        return apiClient.get('/audit-governance/frameworks').then(r => r.data);
    },
    seedFrameworks() {
        return apiClient.post('/audit-governance/frameworks/seed').then(r => r.data);
    },

    // ─── 合规报告 ───
    reports(params) {
        return apiClient.get('/audit-governance/reports', { params }).then(r => r.data);
    },
    generateReport(data) {
        return apiClient.post('/audit-governance/reports/generate', data).then(r => r.data);
    },
    showReport(id) {
        return apiClient.get(`/audit-governance/reports/${id}`).then(r => r.data);
    },
    deleteReport(id) {
        return apiClient.delete(`/audit-governance/reports/${id}`).then(r => r.data);
    },

    // ─── 报告导出 ───
    reportExports(reportId) {
        return apiClient.get(`/audit-governance/reports/${reportId}/exports`).then(r => r.data);
    },
    exportReport(reportId, format) {
        return apiClient.post(`/audit-governance/reports/${reportId}/export`, { format }).then(r => r.data);
    },

    // ─── 治理概览 ───
    governanceDashboard() {
        return apiClient.get('/audit-governance/dashboard').then(r => r.data);
    },
};
