import request from '@/utils/request';

export function getMigrationDashboard() {
    return request.get('/admin/migration-enhancement/dashboard');
}
export function getMigrationImports(params) {
    return request.get('/admin/migration-enhancement/imports', { params });
}
export function createApiImport(data) {
    return request.post('/admin/migration-enhancement/imports/api', data);
}
export function createFileImport(data) {
    return request.post('/admin/migration-enhancement/imports/file', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}
export function runMigrationImport(id) {
    return request.post(`/admin/migration-enhancement/imports/${id}/run`);
}
export function getMigrationImportDetail(id) {
    return request.get(`/admin/migration-enhancement/imports/${id}`);
}
export function getMigrationSources() {
    return request.get('/admin/migration-enhancement/sources');
}
