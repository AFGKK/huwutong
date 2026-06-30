import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/seat-pool/dashboard');
    },

    // License 席位池列表
    listLicenses(params = {}) {
        return client.get('/admin/seat-pool/licenses', { params });
    },

    // License 席位池详情
    getLicenseDetail(id) {
        return client.get(`/admin/seat-pool/licenses/${id}`);
    },

    // 更新席位池配置
    updateConfig(id, data) {
        return client.put(`/admin/seat-pool/licenses/${id}/config`, data);
    },

    // 批量清理过期席位
    batchReleaseExpired() {
        return client.post('/admin/seat-pool/batch-release-expired');
    },

    // 分配历史
    assignmentHistory(params = {}) {
        return client.get('/admin/seat-pool/assignment-history', { params });
    },

    // —— License 级别的操作（复用 LicenseController 端点） ——
    getPoolStatus(licenseId) {
        return client.get(`/licenses/${licenseId}/pool/status`);
    },
    getAssignments(licenseId, params = {}) {
        return client.get(`/licenses/${licenseId}/pool/assignments`, { params });
    },
    getQueue(licenseId) {
        return client.get(`/licenses/${licenseId}/pool/queue`);
    },
    assignSeat(licenseId, data) {
        return client.post(`/licenses/${licenseId}/pool/assign`, data);
    },
    releaseSeat(licenseId, data) {
        return client.post(`/licenses/${licenseId}/pool/release`, data);
    },
    heartbeat(licenseId, seatIdentifier) {
        return client.post(`/licenses/${licenseId}/pool/heartbeat`, { seat_identifier: seatIdentifier });
    },
    cancelQueue(licenseId, seatIdentifier) {
        return client.post(`/licenses/${licenseId}/pool/cancel-queue`, { seat_identifier: seatIdentifier });
    },
};
