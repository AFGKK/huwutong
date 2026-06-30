import client from './client';

export default {
    // 数据中心
    listDataCenters(params = {}) { return client.get('/multi-region/data-centers', { params }); },
    showDataCenter(id) { return client.get(`/multi-region/data-centers/${id}`); },
    storeDataCenter(data) { return client.post('/multi-region/data-centers', data); },
    updateDataCenter(id, data) { return client.put(`/multi-region/data-centers/${id}`, data); },
    destroyDataCenter(id) { return client.delete(`/multi-region/data-centers/${id}`); },
    seedDataCenters() { return client.post('/multi-region/data-centers/seed'); },

    // 健康检查
    healthCheck(id) { return client.post(`/multi-region/data-centers/${id}/health-check`); },
    healthCheckAll() { return client.post('/multi-region/health-check-all'); },
    healthTrend(id, hours = 24) { return client.get(`/multi-region/data-centers/${id}/health-trend`, { params: { hours } }); },

    // 故障切换规则
    listFailoverRules(params = {}) { return client.get('/multi-region/failover-rules', { params }); },
    showFailoverRule(id) { return client.get(`/multi-region/failover-rules/${id}`); },
    storeFailoverRule(data) { return client.post('/multi-region/failover-rules', data); },
    updateFailoverRule(id, data) { return client.put(`/multi-region/failover-rules/${id}`, data); },
    destroyFailoverRule(id) { return client.delete(`/multi-region/failover-rules/${id}`); },
    executeFailover(id, reason) { return client.post(`/multi-region/failover-rules/${id}/execute`, { reason }); },
    executeRestore(id, reason) { return client.post(`/multi-region/failover-rules/${id}/restore`, { reason }); },

    // 自动检测
    autoFailoverCheck() { return client.post('/multi-region/auto-failover-check'); },

    // 日志
    listFailoverLogs(params = {}) { return client.get('/multi-region/failover-logs', { params }); },

    // ─── M3-52 区域部署管理 ───
    listRegionDeployments(params = {}) { return client.get('/multi-region/region-deployments', { params }); },
    showRegionDeployment(id) { return client.get(`/multi-region/region-deployments/${id}`); },
    storeRegionDeployment(data) { return client.post('/multi-region/region-deployments', data); },
    updateRegionDeployment(id, data) { return client.put(`/multi-region/region-deployments/${id}`, data); },
    destroyRegionDeployment(id) { return client.delete(`/multi-region/region-deployments/${id}`); },
    seedRegionDeployments() { return client.post('/multi-region/region-deployments/seed'); },

    // ─── M3-52 跨区域数据同步 ───
    startDataSync(data) { return client.post('/multi-region/data-sync', data); },
    listSyncLogs(params = {}) { return client.get('/multi-region/data-sync/logs', { params }); },

    // ─── M3-52 区域健康检查 ───
    checkAllRegionHealth() { return client.post('/multi-region/region-health/check-all'); },
    regionHealthTrend(regionKey, hours = 24) { return client.get(`/multi-region/region-health/trend/${regionKey}`, { params: { hours } }); },
    crossRegionHealthCheck() { return client.post('/multi-region/region-health/cross-check'); },

    // ─── M3-52 GeoDNS路由 ───
    getOptimalRegion(params = {}) { return client.get('/multi-region/optimal-region', { params }); },

    // 仪表盘
    dashboard() { return client.get('/multi-region/dashboard'); },
};
