import apiClient from './client';

export function getAppeals(params = {}) {
    return apiClient.get('/admin/appeals', { params });
}

export function getAppealStats() {
    return apiClient.get('/admin/appeals/stats');
}

export function getAppeal(id) {
    return apiClient.get(`/admin/appeals/${id}`);
}

export function reviewAppeal(id, data) {
    return apiClient.post(`/admin/appeals/${id}/review`, data);
}

export function startAppealReview(id) {
    return apiClient.post(`/admin/appeals/${id}/start-review`);
}

export function banUser(userId, data = {}) {
    return apiClient.post(`/admin/users/${userId}/ban`, data);
}

export function unbanUser(userId, data = {}) {
    return apiClient.post(`/admin/users/${userId}/unban`, data);
}
