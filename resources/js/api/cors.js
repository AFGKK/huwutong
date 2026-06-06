import apiClient from '@/api/client';

export function getCorsConfigs() {
    return apiClient.get('/cors-configs').then(r => r.data);
}

export function createCorsConfig(data) {
    return apiClient.post('/cors-configs', data).then(r => r.data);
}

export function updateCorsConfig(id, data) {
    return apiClient.put(`/cors-configs/${id}`, data).then(r => r.data);
}

export function deleteCorsConfig(id) {
    return apiClient.delete(`/cors-configs/${id}`).then(r => r.data);
}

export function testCorsConfig(origin, path) {
    return apiClient.post('/cors-configs/test', { origin, path }).then(r => r.data);
}
