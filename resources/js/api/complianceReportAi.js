import request from '@/utils/request';

export function getComplianceReportDashboard() {
    return request.get('/admin/compliance-ai/dashboard');
}
export function generateComplianceReport(framework, language) {
    return request.post('/admin/compliance-ai/generate', { framework, language });
}
export function getComplianceReports(params) {
    return request.get('/admin/compliance-ai/reports', { params });
}
export function getComplianceReportDetail(id) {
    return request.get(`/admin/compliance-ai/reports/${id}`);
}
export function getComplianceFrameworks() {
    return request.get('/admin/compliance-ai/frameworks');
}
