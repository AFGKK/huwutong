/**
 * AI Token 用量计费追踪 API (M2-77)
 */

import request from '@/utils/request';

export function getTokenDashboard() {
    return request.get('/admin/token-meter/dashboard');
}

export function getTokenRecords(params) {
    return request.get('/admin/token-meter', { params });
}

export function recordTokenConsumption(data) {
    return request.post('/admin/token-meter', data);
}

export function getTokenModels() {
    return request.get('/admin/token-meter/models');
}

export function getTokenFeatures() {
    return request.get('/admin/token-meter/features');
}

export function getTokenBudgets(params) {
    return request.get('/admin/token-meter/budgets', { params });
}

export function upsertTokenBudget(data) {
    return request.post('/admin/token-meter/budgets', data);
}

export function getTokenAlerts() {
    return request.get('/admin/token-meter/alerts');
}

export function resolveTokenAlert(id) {
    return request.post(`/admin/token-meter/alerts/${id}/resolve`);
}

export function checkTokenAlerts() {
    return request.post('/admin/token-meter/check-alerts');
}

export function getTenantTokenReport(tenantId, params) {
    return request.get(`/admin/token-meter/tenants/${tenantId}/report`, { params });
}

// ── 成本分摊 ──

export function getCostAllocation(params) {
    return request.get('/admin/token-meter/cost-allocation', { params });
}

export function getAllocationSummary(params) {
    return request.get('/admin/token-meter/allocation-summary', { params });
}

export function exportAllocationCsv(params) {
    return request.get('/admin/token-meter/export-allocation', { params, responseType: 'blob' });
}
