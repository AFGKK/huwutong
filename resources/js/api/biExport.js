import apiClient from './client';

export function getPlatforms() {
    return apiClient.get('/admin/bi/platforms');
}

export function getConfigTemplate(platform) {
    return apiClient.get(`/admin/bi/config-template/${platform}`);
}

export function getStats() {
    return apiClient.get('/admin/bi/stats');
}

export function getConnections() {
    return apiClient.get('/admin/bi/connections');
}

export function createConnection(data) {
    return apiClient.post('/admin/bi/connections', data);
}

export function updateConnection(id, data) {
    return apiClient.put(`/admin/bi/connections/${id}`, data);
}

export function deleteConnection(id) {
    return apiClient.delete(`/admin/bi/connections/${id}`);
}

export function testConnection(id) {
    return apiClient.post(`/admin/bi/connections/${id}/test`);
}

export function getDatasets(connectionId) {
    return apiClient.get(`/admin/bi/connections/${connectionId}/datasets`);
}

export function createDataset(connectionId, data) {
    return apiClient.post(`/admin/bi/connections/${connectionId}/datasets`, data);
}

export function updateDataset(id, data) {
    return apiClient.put(`/admin/bi/datasets/${id}`, data);
}

export function deleteDataset(id) {
    return apiClient.delete(`/admin/bi/datasets/${id}`);
}

export function syncDataset(id) {
    return apiClient.post(`/admin/bi/datasets/${id}/sync`);
}

export function getSyncLogs(id, params) {
    return apiClient.get(`/admin/bi/datasets/${id}/logs`, { params });
}
