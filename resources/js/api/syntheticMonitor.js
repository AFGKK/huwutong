import apiClient from './client';

const syntheticMonitorApi = {
    dashboard() { return apiClient.get('/synthetic-monitor/dashboard'); },
    listRegions() { return apiClient.get('/synthetic-monitor/regions'); },
    seedRegions() { return apiClient.post('/synthetic-monitor/regions/seed'); },
    listProbes(params) { return apiClient.get('/synthetic-monitor/probes', { params }); },
    createProbe(data) { return apiClient.post('/synthetic-monitor/probes', data); },
    regionStats(regionCode, params) { return apiClient.get(`/synthetic-monitor/region-stats/${regionCode}`, { params }); },
    allRegionComparison(params) { return apiClient.get('/synthetic-monitor/comparison', { params }); },
    slaReport(params) { return apiClient.get('/synthetic-monitor/sla-report', { params }); },
    syncToStatusPage() { return apiClient.post('/synthetic-monitor/sync-status-page'); },
    pruneResults() { return apiClient.post('/synthetic-monitor/prune'); },
};

export default syntheticMonitorApi;
