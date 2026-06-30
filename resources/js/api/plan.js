import client from './client';

export default {
    // 管理端
    list(params = {}) { return client.get('/plans', { params }); },
    show(id) { return client.get(`/plans/${id}`); },
    create(data) { return client.post('/billing/plans', data); },
    update(id, data) { return client.put(`/billing/plans/${id}`, data); },
    destroy(id) { return client.delete(`/billing/plans/${id}`); },

    bundleRules() { return client.get('/plans/bundle-rules'); },
    createBundleRule(data) { return client.post('/plans/bundle-rules', data); },
    updateBundleRule(id, data) { return client.put(`/plans/bundle-rules/${id}`, data); },
    deleteBundleRule(id) { return client.delete(`/plans/bundle-rules/${id}`); },

    upgradePaths() { return client.get('/plans/upgrade-paths'); },
    createUpgradePath(data) { return client.post('/plans/upgrade-paths', data); },
    updateUpgradePath(id, data) { return client.put(`/plans/upgrade-paths/${id}`, data); },
    deleteUpgradePath(id) { return client.delete(`/plans/upgrade-paths/${id}`); },

    calculateUpgrade(data) { return client.post('/plans/calculate-upgrade', data); },
    executeUpgrade(subscriptionId, data) { return client.post(`/plans/subscriptions/${subscriptionId}/upgrade`, data); },

    upgradeLogs(params = {}) { return client.get('/plans/upgrade-logs', { params }); },

    // 门户端
    publicPlans(params = {}) { return client.get('/portal/plans', { params }); },
    upgradeOptions(subscriptionId) { return client.get(`/portal/plans/subscriptions/${subscriptionId}/upgrade-options`); },
};
