import apiClient from './client';

export function getEnterpriseSsoStats() {
    return apiClient.get('/admin/enterprise-sso/stats');
}
export function getIdps() {
    return apiClient.get('/admin/enterprise-sso/idps');
}
export function createIdp(data) {
    return apiClient.post('/admin/enterprise-sso/idps', data);
}
export function updateIdp(id, data) {
    return apiClient.put(`/admin/enterprise-sso/idps/${id}`, data);
}
export function deleteIdp(id) {
    return apiClient.delete(`/admin/enterprise-sso/idps/${id}`);
}
export function getSpMetadata(id) {
    return apiClient.get(`/admin/enterprise-sso/idps/${id}/sp-metadata`, { responseType: 'text' });
}
export function parseMetadata(xml) {
    return apiClient.post('/admin/enterprise-sso/parse-metadata', { metadata_xml: xml });
}
export function getDomains(id) {
    return apiClient.get(`/admin/enterprise-sso/idps/${id}/domains`);
}
export function createDomain(id, data) {
    return apiClient.post(`/admin/enterprise-sso/idps/${id}/domains`, data);
}
export function deleteDomain(id) {
    return apiClient.delete(`/admin/enterprise-sso/domains/${id}`);
}
export function getGroupMappings(id) {
    return apiClient.get(`/admin/enterprise-sso/idps/${id}/group-mappings`);
}
export function createGroupMapping(id, data) {
    return apiClient.post(`/admin/enterprise-sso/idps/${id}/group-mappings`, data);
}
export function getJitRules(id) {
    return apiClient.get(`/admin/enterprise-sso/idps/${id}/jit-rules`);
}
export function createJitRule(id, data) {
    return apiClient.post(`/admin/enterprise-sso/idps/${id}/jit-rules`, data);
}
export function healthCheck(id) {
    return apiClient.post(`/admin/enterprise-sso/idps/${id}/health-check`);
}
export function resolveDomain(email) {
    return apiClient.post('/admin/enterprise-sso/resolve-domain', { email });
}
