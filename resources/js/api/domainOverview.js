import apiClient from './client';

export function getDomainOverview() {
    return apiClient.get('/domain-overview/');
}

export function getDomainList(params = {}) {
    return apiClient.get('/domain-overview/domains', { params });
}

export function updatePlatform(data) {
    return apiClient.put('/domain-overview/platform', data);
}

export function getDnsStatus() {
    return apiClient.get('/domain-overview/dns-status');
}

export function renewDomainSsl(domainId) {
    return apiClient.post(`/domain-overview/domains/${domainId}/renew-ssl`);
}

export function batchRenewDomainSsl(domainIds) {
    return apiClient.post('/domain-overview/domains/batch-renew-ssl', { domain_ids: domainIds });
}

export function updateTenantDomain(tenantId, data) {
    return apiClient.put(`/domain-overview/tenants/${tenantId}/domain`, data);
}
