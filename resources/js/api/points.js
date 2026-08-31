import apiClient from './client';

export function getAdminStats() {
    return apiClient.get('/points/admin/stats');
}

export function getAdminUsers(params = {}) {
    return apiClient.get('/points/admin/users', { params });
}

export function getAdminTransactions(params = {}) {
    return apiClient.get('/points/admin/transactions', { params });
}

export function grantPoints(data) {
    return apiClient.post('/points/grant', data);
}

export default {
    getAdminStats,
    getAdminUsers,
    getAdminTransactions,
    grantPoints,
};
