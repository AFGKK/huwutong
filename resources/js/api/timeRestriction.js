import request from '@/utils/request';

export default {
    getConfig(licenseId) {
        return request.get(`/api/admin/licenses/${licenseId}/time-restriction`);
    },

    saveConfig(licenseId, data) {
        return request.post(`/api/admin/licenses/${licenseId}/time-restriction`, data);
    },

    deleteConfig(licenseId) {
        return request.delete(`/api/admin/licenses/${licenseId}/time-restriction`);
    },

    getLogs(licenseId, params) {
        return request.get(`/api/admin/licenses/${licenseId}/time-restriction/logs`, { params });
    },

    getMetadata() {
        return request.get('/api/admin/time-restriction/metadata');
    },

    // SDK 侧
    checkAccess(licenseKey) {
        return request.post('/api/license/time-restriction-check', { license_key: licenseKey });
    },

    // ─── 新增：全局管理 ───

    listAll(params = {}) {
        return request.get('/api/admin/time-restriction', { params });
    },

    getStats() {
        return request.get('/api/admin/time-restriction/stats');
    },

    getGlobalLogs(params = {}) {
        return request.get('/api/admin/time-restriction/logs', { params });
    },
};
