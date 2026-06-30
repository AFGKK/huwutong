import apiClient from './client';

export function getLicenses(params) {
    return apiClient.get('/licenses', { params });
}

export default {
    list(params) {
        return apiClient.get('/licenses', { params });
    },
    show(id) {
        return apiClient.get(`/licenses/${id}`);
    },
    create(data) {
        return apiClient.post('/licenses', data);
    },
    update(id, data) {
        return apiClient.put(`/licenses/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/licenses/${id}`);
    },
    restoreFromTrash(id) {
        return apiClient.post(`/licenses/${id}/restore`);
    },
    stats() {
        return apiClient.get('/licenses/stats');
    },

    // 状态操作
    revoke(id) {
        return apiClient.post(`/licenses/${id}/revoke`);
    },
    suspend(id) {
        return apiClient.post(`/licenses/${id}/suspend`);
    },
    freeze(id) {
        return apiClient.post(`/licenses/${id}/freeze`);
    },
    restore(id) {
        return apiClient.post(`/licenses/${id}/restore`);
    },
    blacklist(id) {
        return apiClient.post(`/licenses/${id}/blacklist`);
    },
    refund(id) {
        return apiClient.post(`/licenses/${id}/refund`);
    },

    // 批量
    batchStore(data) {
        return apiClient.post('/licenses/batch', data);
    },
    batchOperation(data) {
        return apiClient.post('/licenses/batch/operation', data);
    },
    lookup(params) {
        return apiClient.post('/licenses/lookup', params);
    },

    // 导入/导出
    exportCsv(params) {
        return apiClient.get('/licenses/export', { params, responseType: 'blob' });
    },
    import(formData) {
        return apiClient.post('/licenses/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    // Seat Pool 席位池管理 (M3-45)
    poolStatus(licenseId) {
        return apiClient.get(`/licenses/${licenseId}/pool/status`);
    },
    poolAssignments(licenseId, params) {
        return apiClient.get(`/licenses/${licenseId}/pool/assignments`, { params });
    },
    poolQueue(licenseId) {
        return apiClient.get(`/licenses/${licenseId}/pool/queue`);
    },
    poolAssign(licenseId, data) {
        return apiClient.post(`/licenses/${licenseId}/pool/assign`, data);
    },
    poolRelease(licenseId, data) {
        return apiClient.post(`/licenses/${licenseId}/pool/release`, data);
    },
    poolHeartbeat(licenseId, data) {
        return apiClient.post(`/licenses/${licenseId}/pool/heartbeat`, data);
    },
    poolCancelQueue(licenseId, data) {
        return apiClient.post(`/licenses/${licenseId}/pool/cancel-queue`, data);
    },
    poolUpdateConfig(licenseId, data) {
        return apiClient.put(`/licenses/${licenseId}/pool/config`, data);
    },
    poolBatchReleaseExpired() {
        return apiClient.post('/licenses/pool/batch-release-expired');
    },
};
