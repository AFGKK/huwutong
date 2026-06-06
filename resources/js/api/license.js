import apiClient from './client';

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
    lookup(params) {
        return apiClient.post('/licenses/lookup', params);
    },
};
