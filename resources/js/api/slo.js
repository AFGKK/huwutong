import apiClient from '@/api/client'

export function getSloDashboard(period = '7d') {
    return apiClient.get('/admin/slo/dashboard', { params: { period } });
}

export function getSloList(params = {}) {
    return apiClient.get('/admin/slo', { params });
}

export function getSlo(id) {
    return apiClient.get(`/admin/slo/${id}`);
}

export function createSlo(data) {
    return apiClient.post('/admin/slo', data);
}

export function updateSlo(id, data) {
    return apiClient.put(`/admin/slo/${id}`, data);
}

export function deleteSlo(id) {
    return apiClient.delete(`/admin/slo/${id}`);
}

export function calculateSlo(id) {
    return apiClient.post(`/admin/slo/${id}/calculate`);
}

export function calculateAllSlo() {
    return apiClient.post('/admin/slo/calculate-all');
}

export function getSliTypes() {
    return apiClient.get('/admin/slo/meta/sli-types');
}

export function getTracingList(params = {}) {
    return apiClient.get('/admin/tracing', { params });
}

export function getTracingStats(params = {}) {
    return apiClient.get('/admin/tracing/stats', { params });
}

export function getTracingDetail(id) {
    return apiClient.get(`/admin/tracing/${id}`);
}
