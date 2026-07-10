import request from '@/utils/request';

export default {
    getConfig(licenseId) {
        return request.get(`/admin/licenses/${licenseId}/time-restriction`);
    },

    saveConfig(licenseId, data) {
        return request.post(`/admin/licenses/${licenseId}/time-restriction`, data);
    },

    deleteConfig(licenseId) {
        return request.delete(`/admin/licenses/${licenseId}/time-restriction`);
    },

    getLogs(licenseId, params) {
        return request.get(`/admin/licenses/${licenseId}/time-restriction/logs`, { params });
    },

    getMetadata() {
        return request.get('/admin/time-restriction/metadata');
    },

    // SDK 侧
    checkAccess(licenseKey) {
        return request.post('/license/time-restriction-check', { license_key: licenseKey });
    },

    // ─── 新增：全局管理 ───

    listAll(params = {}) {
        return request.get('/admin/time-restriction', { params });
    },

    getStats() {
        return request.get('/admin/time-restriction/stats');
    },

    getGlobalLogs(params = {}) {
        return request.get('/admin/time-restriction/logs', { params });
    },
};
