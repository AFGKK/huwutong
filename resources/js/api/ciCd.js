import apiClient from './client';

export function getCiTokens(params = {}) {
    return apiClient.get('/admin/ci/tokens', { params });
}

export function createCiToken(data) {
    return apiClient.post('/admin/ci/tokens', data);
}

export function updateCiToken(id, data) {
    return apiClient.put(`/admin/ci/tokens/${id}`, data);
}

export function deleteCiToken(id) {
    return apiClient.delete(`/admin/ci/tokens/${id}`);
}

export function getCiTokenLogs(id, params = {}) {
    return apiClient.get(`/admin/ci/tokens/${id}/logs`, { params });
}

export function getCiStats() {
    return apiClient.get('/admin/ci/stats');
}

export function getCiExamples() {
    return apiClient.get('/ci/examples');
}
