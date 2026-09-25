import apiClient from './client';

export default {
    /** 管理后台设备列表 */
    list(params) {
        return apiClient.get('/devices', { params });
    },
    /** 门户：当前用户设备（优化方案 2.2） */
    myList(params) {
        return apiClient.get('/user/devices', { params });
    },
    show(id) {
        return apiClient.get(`/devices/${id}`);
    },
    deactivate(id, blacklist = false) {
        return apiClient.post(`/devices/${id}/deactivate`, { blacklist });
    },
    stats() {
        return apiClient.get('/devices/stats');
    },
    batch(ids, action) {
        return apiClient.post('/devices/batch', { ids, action });
    },
    profile(id) {
        return apiClient.get(`/devices/${id}/profile`);
    },
    profileStats() {
        return apiClient.get('/devices/profile-stats');
    },
    timeline(id) {
        return apiClient.get(`/devices/${id}/timeline`);
    },
    lifecycleEvents(id, params = {}) {
        return apiClient.get(`/devices/${id}/lifecycle-events`, { params });
    },
    adjustTrust(id, delta, reason) {
        return apiClient.post(`/devices/${id}/adjust-trust`, { delta, reason });
    },
    markSuspicious(id, reason) {
        return apiClient.post(`/devices/${id}/mark-suspicious`, { reason });
    },
    retire(id, reason) {
        return apiClient.post(`/devices/${id}/retire`, { reason });
    },
};
