import apiClient from './client';

export default {
    // ─── 合规报告 ───
    frameworks() {
        return apiClient.get('/audit-governance/frameworks').then(r => r.data);
    },
    seedFrameworks() {
        return apiClient.post('/audit-governance/frameworks/seed').then(r => r.data);
    },
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

    // ─── 审计日志标签 ───
    tags() {
        return apiClient.get('/audit-governance/tags').then(r => r.data);
    },
    createTag(data) {
        return apiClient.post('/audit-governance/tags', data).then(r => r.data);
    },
    updateTag(id, data) {
        return apiClient.put(`/audit-governance/tags/${id}`, data).then(r => r.data);
    },
    deleteTag(id) {
        return apiClient.delete(`/audit-governance/tags/${id}`).then(r => r.data);
    },

    // ─── 批量标记 ───
    batchTag(data) {
        return apiClient.post('/audit-governance/audit-logs/batch-tag', data).then(r => r.data);
    },

    // ─── 审计日志备注 ───
    annotations(logId) {
        return apiClient.get(`/audit-governance/audit-logs/${logId}/annotations`).then(r => r.data);
    },
    addAnnotation(logId, content) {
        return apiClient.post(`/audit-governance/audit-logs/${logId}/annotations`, { content }).then(r => r.data);
    },
    deleteAnnotation(id) {
        return apiClient.delete(`/audit-governance/audit-logs/annotations/${id}`).then(r => r.data);
    },

    // ─── 批量操作历史 ───
    batchOperations() {
        return apiClient.get('/audit-governance/batch-operations').then(r => r.data);
    },

    // ─── 数据保留治理 ───
    retentionDashboard() {
        return apiClient.get('/audit-governance/retention-dashboard').then(r => r.data);
    },
    executeCleanup(data) {
        return apiClient.post('/audit-governance/cleanup', data).then(r => r.data);
    },
    cleanupHistory() {
        return apiClient.get('/audit-governance/cleanup-history').then(r => r.data);
    },

    // ─── 多数据源保留策略管理 ───
    retentionPolicies() {
        return apiClient.get('/audit-governance/retention-policies').then(r => r.data);
    },
    saveRetentionPolicy(data) {
        return apiClient.post('/audit-governance/retention-policies', data).then(r => r.data);
    },
    toggleRetentionPolicy(id) {
        return apiClient.post(`/audit-governance/retention-policies/${id}/toggle`).then(r => r.data);
    },
    deleteRetentionPolicy(id) {
        return apiClient.delete(`/audit-governance/retention-policies/${id}`).then(r => r.data);
    },

    // ─── 增强数据保留仪表盘 ───
    extendedRetentionDashboard() {
        return apiClient.get('/audit-governance/extended-dashboard').then(r => r.data);
    },
    executeExtendedCleanup(data) {
        return apiClient.post('/audit-governance/extended-cleanup', data).then(r => r.data);
    },

    // ─── 清理调度配置 ───
    cleanupSchedules() {
        return apiClient.get('/audit-governance/cleanup-schedules').then(r => r.data);
    },
    saveCleanupSchedule(data) {
        return apiClient.post('/audit-governance/cleanup-schedules', data).then(r => r.data);
    },

    // ─── 合规报告导出 ───
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
