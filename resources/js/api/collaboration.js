import apiClient from './client';

export default {
    // ─── 笔记 ───
    getNotes(entityType, entityId, params = {}) {
        return apiClient.get(`/admin/${entityType}/${entityId}/notes`, { params }).then(r => r.data);
    },
    createNote(entityType, entityId, data) {
        return apiClient.post(`/admin/${entityType}/${entityId}/notes`, data).then(r => r.data);
    },
    updateNote(id, data) {
        return apiClient.put(`/admin/notes/${id}`, data).then(r => r.data);
    },
    deleteNote(id) {
        return apiClient.delete(`/admin/notes/${id}`).then(r => r.data);
    },
    togglePin(id) {
        return apiClient.post(`/admin/notes/${id}/toggle-pin`).then(r => r.data);
    },
    getNoteCounts(entityType, entityIds) {
        return apiClient.post('/admin/notes/counts', { entity_type: entityType, entity_ids: entityIds }).then(r => r.data);
    },

    // ─── 变更日志 ───
    getChangeLogs(entityType, entityId) {
        return apiClient.get(`/admin/${entityType}/${entityId}/change-logs`).then(r => r.data);
    },

    // ─── 活动流 ───
    getActivityFeed(params = {}) {
        return apiClient.get('/admin/activity-feed', { params }).then(r => r.data);
    },
    getMyActivityFeed() {
        return apiClient.get('/admin/activity-feed/mine').then(r => r.data);
    },
    getLastActivityTimestamps(entityType, entityIds) {
        return apiClient.post('/admin/activities/last-timestamps', {
            entity_type: entityType,
            entity_ids: entityIds,
        }).then(r => r.data);
    },

    // ─── 快捷回复 ───
    getCannedReplies(params = {}) {
        return apiClient.get('/admin/canned-replies', { params }).then(r => r.data);
    },
    createCannedReply(data) {
        return apiClient.post('/admin/canned-replies', data).then(r => r.data);
    },
    updateCannedReply(id, data) {
        return apiClient.put(`/admin/canned-replies/${id}`, data).then(r => r.data);
    },
    deleteCannedReply(id) {
        return apiClient.delete(`/admin/canned-replies/${id}`).then(r => r.data);
    },

    // ─── 关注 ───
    getWatchlist(params = {}) {
        return apiClient.get('/admin/watchlist', { params }).then(r => r.data);
    },
    isWatching(entityType, entityId) {
        return apiClient.get(`/admin/${entityType}/${entityId}/is-watching`).then(r => r.data);
    },
    toggleWatch(entityType, entityId) {
        return apiClient.post(`/admin/${entityType}/${entityId}/toggle-watch`).then(r => r.data);
    },

    // ─── 协作偏好 ───
    getPreferences() {
        return apiClient.get('/admin/collaboration-preferences').then(r => r.data);
    },
    updatePreferences(data) {
        return apiClient.put('/admin/collaboration-preferences', data).then(r => r.data);
    },
};
