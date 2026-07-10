import apiClient from './client';

export function getAccountingIntegrations() {
    return apiClient.get('/admin/accounting');
}

export function createAccountingIntegration(data) {
    return apiClient.post('/admin/accounting', data);
}

export function updateAccountingIntegration(id, data) {
    return apiClient.put(`/admin/accounting/${id}`, data);
}

export function deleteAccountingIntegration(id) {
    return apiClient.delete(`/admin/accounting/${id}`);
}

export function getAccountingAuthorizeUrl(id) {
    return apiClient.get(`/admin/accounting/${id}/authorize-url`);
}

export function testAccountingConnection(id) {
    return apiClient.post(`/admin/accounting/${id}/test`);
}

export function syncPendingAccounting(id) {
    return apiClient.post(`/admin/accounting/${id}/sync-pending`);
}

export function syncAccountingInvoice(id, invoiceId) {
    return apiClient.post(`/admin/accounting/${id}/sync-invoice/${invoiceId}`);
}

export function getAccountingLogs(id, params = {}) {
    return apiClient.get(`/admin/accounting/${id}/logs`, { params });
}

export function getAccountingMappings(id, params = {}) {
    return apiClient.get(`/admin/accounting/${id}/mappings`, { params });
}
