import client from './client';

/**
 * M2-128 收益通知 API
 */
export default {
    list(params = {}) {
        return client.get('/earnings/notifications', { params });
    },
    stats() {
        return client.get('/earnings/notifications/stats');
    },
    preferences() {
        return client.get('/earnings/notifications/preferences');
    },
    updatePreferences(data) {
        return client.put('/earnings/notifications/preferences', data);
    },
    markRead(id) {
        return client.post(`/earnings/notifications/${id}/read`);
    },
    markAllRead(type = null) {
        const params = type ? { type } : {};
        return client.post('/earnings/notifications/mark-all-read', params);
    },
};
