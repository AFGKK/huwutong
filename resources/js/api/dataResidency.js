import apiClient from './client';

const BASE = '/admin/data-residency';

export default {
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },
    getRegions() {
        return apiClient.get(`${BASE}/regions`).then(r => r.data);
    },
    getRecords(params) {
        return apiClient.get(`${BASE}/records`, { params }).then(r => r.data);
    },
    assignTenantRegion(tenantId, region) {
        return apiClient.post(`${BASE}/assign-tenant`, { tenant_id: tenantId, region }).then(r => r.data);
    },
    createRecord(tenantId, regionCode, classification) {
        return apiClient.post(`${BASE}/create-record`, { tenant_id: tenantId, region_code: regionCode, data_classification: classification }).then(r => r.data);
    },
    resolveTarget(tenantId, classification) {
        return apiClient.post(`${BASE}/resolve-target`, { tenant_id: tenantId, data_classification: classification }).then(r => r.data);
    },
    startMigration(tenantId, sourceRegion, targetRegion, classification) {
        return apiClient.post(`${BASE}/start-migration`, { tenant_id: tenantId, source_region: sourceRegion, target_region: targetRegion, data_classification: classification }).then(r => r.data);
    },
    getMigrations(params) {
        return apiClient.get(`${BASE}/migrations`, { params }).then(r => r.data);
    },
    getComplianceAudit() {
        return apiClient.get(`${BASE}/compliance-audit`).then(r => r.data);
    },
    getClassifications() {
        return apiClient.get(`${BASE}/classifications`).then(r => r.data);
    },
    getTenants() {
        return apiClient.get(`${BASE}/tenants`).then(r => r.data);
    },
};
