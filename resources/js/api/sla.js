import client from './client';

export default {
    initialize() {
        return client.post('/sla/initialize');
    },
    getTiers() {
        return client.get('/sla/tiers');
    },
    upsertTier(data, id = null) {
        if (id) {
            return client.put(`/sla/tiers/${id}`, data);
        }
        return client.post('/sla/tiers', data);
    },
    deleteTier(id) {
        return client.delete(`/sla/tiers/${id}`);
    },
    getCustomerTier(customerId) {
        return client.get(`/sla/customer/${customerId}/tier`);
    },
    assignTier(data) {
        return client.post('/sla/assign', data);
    },
    resetTier(customerId) {
        return client.delete(`/sla/customer/${customerId}/tier`);
    },
    getAuditLog(params = {}) {
        return client.get('/sla/audit-log', { params });
    },
    processExpired() {
        return client.post('/sla/process-expired');
    },
};
