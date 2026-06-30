import request from '../utils/request';

// ─── 客户门户 ───

export function getExportTypes() {
    return request.get('/portal/data-exports/types');
}

export function createExport(data) {
    return request.post('/portal/data-exports', data);
}

export function getMyExports() {
    return request.get('/portal/data-exports/my');
}

export function downloadExport(id) {
    return request.get(`/portal/data-exports/${id}/download`, { responseType: 'blob' });
}

export function deleteExport(id) {
    return request.delete(`/portal/data-exports/${id}`);
}

// ─── 管理员 ───

export function getAdminExports(params = {}) {
    return request.get('/admin/data-exports', { params });
}

export function getExportStats() {
    return request.get('/admin/data-exports/stats');
}

export function adminCreateExport(data) {
    return request.post('/admin/data-exports', data);
}
