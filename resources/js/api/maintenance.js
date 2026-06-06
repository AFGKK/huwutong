import apiClient from '@/api/client';

export function getMaintenanceStatus() {
    return apiClient.get('/maintenance/status').then(r => r.data);
}

export function enableMaintenance(data) {
    return apiClient.post('/maintenance/enable', data).then(r => r.data);
}

export function disableMaintenance() {
    return apiClient.post('/maintenance/disable').then(r => r.data);
}

export function updateMaintenanceConfig(id, data) {
    return apiClient.put(`/maintenance/configs/${id}`, data).then(r => r.data);
}

export function getMaintenanceHistory() {
    return apiClient.get('/maintenance/history').then(r => r.data);
}
