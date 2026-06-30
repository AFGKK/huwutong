import request from '@/utils/request';

export function getPiracyDashboard() {
    return request.get('/admin/piracy-trace/dashboard');
}
export function getScanTasks(params) {
    return request.get('/admin/piracy-trace/scan-tasks', { params });
}
export function createScanTask(data) {
    return request.post('/admin/piracy-trace/scan-tasks', data);
}
export function runScanTask(id) {
    return request.post(`/admin/piracy-trace/scan-tasks/${id}/run`);
}
export function getEvidence(params) {
    return request.get('/admin/piracy-trace/evidence', { params });
}
export function getEvidenceDetail(id) {
    return request.get(`/admin/piracy-trace/evidence/${id}`);
}
export function updateEvidence(id, data) {
    return request.put(`/admin/piracy-trace/evidence/${id}`, data);
}
export function autoRemediate(id) {
    return request.post(`/admin/piracy-trace/evidence/${id}/remediate`);
}
export function generateReport(id) {
    return request.post(`/admin/piracy-trace/evidence/${id}/report`);
}
export function getForensicReports(params) {
    return request.get('/admin/piracy-trace/reports', { params });
}
export function getReportDetail(id) {
    return request.get(`/admin/piracy-trace/reports/${id}`);
}
