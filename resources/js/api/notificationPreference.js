import request from '../utils/request';

// ─── 客户门户 ───

export function getMyNotificationPreferences() {
    return request.get('/portal/notification-preferences');
}

export function updateMyNotificationPreferences(data) {
    return request.put('/portal/notification-preferences', data);
}

export function initializeNotificationPreferences() {
    return request.post('/portal/notification-preferences/initialize');
}

export function checkNotification(channel, category) {
    return request.get('/portal/notification-preferences/check', { params: { channel, category } });
}

// M3-29 增强
export function updateGeneralSettings(data) {
    return request.patch('/portal/notification-preferences/general', data);
}

export function resolveNotificationChannels(category) {
    return request.get('/portal/notification-preferences/resolve-channels', { params: { category } });
}

// ─── 管理员 ───

export function getAdminNotificationPreferences(params = {}) {
    return request.get('/admin/notification-preferences', { params });
}

export function getNotificationPreferenceStats() {
    return request.get('/admin/notification-preferences/stats');
}

export function getUserNotificationPreferences(userId) {
    return request.get(`/admin/notification-preferences/users/${userId}`);
}

// M3-29 增强 - 管理端
export function adminBatchUpdatePreferences(data) {
    return request.post('/admin/notification-preferences/batch-update', data);
}

export function adminUpdateUserGeneral(userId, data) {
    return request.patch(`/admin/notification-preferences/users/${userId}/general`, data);
}

export function adminInitializeUserPreferences(userId) {
    return request.post(`/admin/notification-preferences/users/${userId}/initialize`);
}
