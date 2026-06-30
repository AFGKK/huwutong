import request from '@/utils/request';

export function getReconDashboard() {
    return request.get('/admin/reconciliation/dashboard');
}

export function getReconciliations(params) {
    return request.get('/admin/reconciliation/reconciliations', { params });
}

export function resolveReconciliation(id, data) {
    return request.post(`/admin/reconciliation/reconciliations/${id}/resolve`, data);
}

export function getImports(params) {
    return request.get('/admin/reconciliation/imports', { params });
}

export function importCsv(data) {
    return request.post('/admin/reconciliation/imports/csv', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

export function getChannelRows(params) {
    return request.get('/admin/reconciliation/channel-rows', { params });
}

export function manualMatch(data) {
    return request.post('/admin/reconciliation/manual-match', data);
}

export function getCalendars(params) {
    return request.get('/admin/reconciliation/calendars', { params });
}

export function generateCalendars(data) {
    return request.post('/admin/reconciliation/calendars/generate', data);
}

export function getReport(params) {
    return request.get('/admin/reconciliation/report', { params });
}
