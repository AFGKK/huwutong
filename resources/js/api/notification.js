import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/notifications', { params });
    },
    unreadCount() {
        return apiClient.get('/notifications/unread-count');
    },
    markRead(id) {
        return apiClient.post(`/notifications/${id}/read`);
    },
    markAllRead() {
        return apiClient.post('/notifications/read-all');
    },
    batch(ids, action) {
        return apiClient.post('/notifications/batch', { ids, action });
    },
    destroy(id) {
        return apiClient.delete(`/notifications/${id}`);
    },
    preferences() {
        return apiClient.get('/notifications/preferences');
    },
    updatePreferences(data) {
        return apiClient.put('/notifications/preferences', data);
    },
};
