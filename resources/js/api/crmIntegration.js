import request from '@/utils/request';

export function getCrmDashboard() {
    return request.get('/admin/crm/dashboard');
}
export function connectCrm(provider, credentials) {
    return request.post('/admin/crm/connect', { provider, credentials });
}
export function disconnectCrm(id) {
    return request.post(`/admin/crm/${id}/disconnect`);
}
export function pushToCrm(id, entityType, ids) {
    return request.post(`/admin/crm/${id}/push`, { entity_type: entityType, ids });
}
export function pullFromCrm(id, entityType) {
    return request.post(`/admin/crm/${id}/pull`, { entity_type: entityType });
}
export function getCrmLogs(id) {
    return request.get(`/admin/crm/${id}/logs`);
}
export function getCrmConnection(id) {
    return request.get(`/admin/crm/${id}`);
}
