import request from '@/utils/request';

export function getMigrationAssistantDashboard() {
    return request.get('/admin/migration-assistant/dashboard');
}
export function getMigrationJobs(params) {
    return request.get('/admin/migration-assistant/jobs', { params });
}
export function createMigrationJob(data) {
    return request.post('/admin/migration-assistant/jobs', data);
}
export function runMigrationJob(id) {
    return request.post(`/admin/migration-assistant/jobs/${id}/run`);
}
export function getMigrationJobDetail(id) {
    return request.get(`/admin/migration-assistant/jobs/${id}`);
}
export function getMigrationSources() {
    return request.get('/admin/migration-assistant/sources');
}
