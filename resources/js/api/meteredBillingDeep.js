import apiClient from './client';

export function getMeteredStats() {
    return apiClient.get('/admin/metered-billing/stats');
}

export function getTieredPricings() {
    return apiClient.get('/admin/metered-billing/tiered-pricings');
}

export function createTieredPricing(data) {
    return apiClient.post('/admin/metered-billing/tiered-pricings', data);
}

export function deleteTieredPricing(id) {
    return apiClient.delete(`/admin/metered-billing/tiered-pricings/${id}`);
}

export function getAlerts() {
    return apiClient.get('/admin/metered-billing/alerts');
}

export function createAlert(data) {
    return apiClient.post('/admin/metered-billing/alerts', data);
}

export function updateAlert(id, data) {
    return apiClient.put(`/admin/metered-billing/alerts/${id}`, data);
}

export function deleteAlert(id) {
    return apiClient.delete(`/admin/metered-billing/alerts/${id}`);
}

export function getAlertHistories(id) {
    return apiClient.get(`/admin/metered-billing/alerts/${id}/histories`);
}

export function evaluateAlerts() {
    return apiClient.post('/admin/metered-billing/evaluate-alerts');
}

export function getAutoSwitchRules() {
    return apiClient.get('/admin/metered-billing/auto-switch-rules');
}

export function createAutoSwitchRule(data) {
    return apiClient.post('/admin/metered-billing/auto-switch-rules', data);
}

export function updateAutoSwitchRule(id, data) {
    return apiClient.put(`/admin/metered-billing/auto-switch-rules/${id}`, data);
}

export function deleteAutoSwitchRule(id) {
    return apiClient.delete(`/admin/metered-billing/auto-switch-rules/${id}`);
}

export function evaluateAutoSwitch() {
    return apiClient.post('/admin/metered-billing/evaluate-auto-switch');
}
