import apiClient from '@/api/client';

export function getCspConfigs() {
    return apiClient.get('/csp-configs').then(r => r.data);
}

export function createCspConfig(data) {
    return apiClient.post('/csp-configs', data).then(r => r.data);
}

export function updateCspConfig(id, data) {
    return apiClient.put(`/csp-configs/${id}`, data).then(r => r.data);
}

export function deleteCspConfig(id) {
    return apiClient.delete(`/csp-configs/${id}`).then(r => r.data);
}

export function previewCspDirectives(directives) {
    return apiClient.post('/csp-configs/preview', { directives }).then(r => r.data);
}

export function getCspViolations(params) {
    return apiClient.get('/csp-violations', { params }).then(r => r.data);
}

export function getCspViolationStats() {
    return apiClient.get('/csp-violations/stats').then(r => r.data);
}
