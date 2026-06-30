import apiClient from './client';

export default {
    // ─── 水印管理 ───
    embedWatermark(licenseId, sourceInfo) {
        return apiClient.post(`/admin/licenses/${licenseId}/watermark`, { source_info: sourceInfo }).then(r => r.data);
    },
    extractWatermark(licenseId) {
        return apiClient.get(`/admin/licenses/${licenseId}/watermark`).then(r => r.data);
    },
    revokeWatermark(watermarkId) {
        return apiClient.delete(`/admin/watermarks/${watermarkId}`).then(r => r.data);
    },
    traceWatermark(key) {
        return apiClient.get('/admin/watermarks/trace', { params: { key } }).then(r => r.data);
    },
    searchWatermarks(q) {
        return apiClient.get('/admin/watermarks/search', { params: { q } }).then(r => r.data);
    },
    watermarks(params) {
        return apiClient.get('/admin/watermarks', { params }).then(r => r.data);
    },

    // ─── 完整性验证 ───
    verifyIntegrity(licenseId) {
        return apiClient.post(`/admin/licenses/${licenseId}/verify-integrity`).then(r => r.data);
    },
    refreshHash(licenseId) {
        return apiClient.post(`/admin/licenses/${licenseId}/refresh-hash`).then(r => r.data);
    },

    // ─── 验证日志 ───
    verificationLogs(licenseId) {
        return apiClient.get(`/admin/licenses/${licenseId}/verification-logs`).then(r => r.data);
    },
    verificationStats() {
        return apiClient.get('/admin/watermark-tamper/verification-stats').then(r => r.data);
    },

    // ─── 防篡改事件 ───
    tamperEvents(params) {
        return apiClient.get('/admin/tamper-events', { params }).then(r => r.data);
    },
    resolveEvent(eventId, resolution) {
        return apiClient.post(`/admin/tamper-events/${eventId}/resolve`, { resolution }).then(r => r.data);
    },

    // ─── 防篡改策略 ───
    tamperPolicies() {
        return apiClient.get('/admin/tamper-policies').then(r => r.data);
    },
    updatePolicy(id, data) {
        return apiClient.put(`/admin/tamper-policies/${id}`, data).then(r => r.data);
    },

    // ─── 仪表盘 ───
    dashboard() {
        return apiClient.get('/admin/watermark-tamper/dashboard').then(r => r.data);
    },
};
