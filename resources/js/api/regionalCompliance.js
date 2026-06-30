import request from '@/utils/request';

export function getRegionalComplianceDashboard() {
    return request.get('/admin/regional-compliance/dashboard');
}
export function initializeRegionalCompliance() {
    return request.post('/admin/regional-compliance/initialize');
}
export function getRegionalComplianceConfigs() {
    return request.get('/admin/regional-compliance/configs');
}
export function updateRegionalComplianceConfig(regionKey, data) {
    return request.put(`/admin/regional-compliance/configs/${regionKey}`, data);
}
export function checkRegionalComplianceStatus(regionKey) {
    return request.get(`/admin/regional-compliance/status/${regionKey}`);
}
export function getRegionalRestrictions(params) {
    return request.get('/admin/regional-compliance/restrictions', { params });
}
export function addRegionalRestriction(data) {
    return request.post('/admin/regional-compliance/restrictions', data);
}
export function removeRegionalRestriction(id) {
    return request.delete(`/admin/regional-compliance/restrictions/${id}`);
}
export function checkProductRegionalEligibility(data) {
    return request.post('/admin/regional-compliance/check-eligibility', data);
}
export function generateRegionalComplianceSummary() {
    return request.post('/admin/regional-compliance/generate-summary');
}
export function getRegionalComplianceLogs(params) {
    return request.get('/admin/regional-compliance/logs', { params });
}
export function getAvailableRegions() {
    return request.get('/admin/regional-compliance/available-regions');
}
