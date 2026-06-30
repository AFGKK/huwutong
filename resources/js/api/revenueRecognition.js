import apiClient from './client';

export default {
    getSchedules(params = {}) {
        return apiClient.get('/api/revenue/schedules', { params });
    },
    getSchedule(id) {
        return apiClient.get(`/api/revenue/schedules/${id}`);
    },
    processRecognition(data = {}) {
        return apiClient.post('/api/revenue/process', data);
    },
    createSchedules(data = {}) {
        return apiClient.post('/api/revenue/create-schedules', data);
    },
    getSummary() {
        return apiClient.get('/api/revenue/summary');
    },
    getAsc606Report(params) {
        return apiClient.get('/api/revenue/asc606-report', { params });
    },
    getMonthlySnapshots(params = {}) {
        return apiClient.get('/api/revenue/monthly-snapshots', { params });
    },
    generateSnapshot(data = {}) {
        return apiClient.post('/api/revenue/generate-snapshot', data);
    },

    // M3-55 增强
    cancelSchedule(id, data = {}) {
        return apiClient.post(`/api/revenue/schedules/${id}/cancel`, data);
    },
    recomputeSchedule(id) {
        return apiClient.post(`/api/revenue/schedules/${id}/recompute`);
    },
    exportAsc606Report(params) {
        return apiClient.get('/api/revenue/asc606-report/export', { params, responseType: 'blob' });
    },

    // MRR 瀑布图 (M3-59)
    getMrrWaterfall(params = {}) {
        return apiClient.get('/api/revenue/mrr-waterfall', { params });
    },
    getMrrDrilldown(params = {}) {
        return apiClient.get('/api/revenue/mrr-drilldown', { params });
    },
    getMrrSummary(params = {}) {
        return apiClient.get('/api/revenue/mrr-summary', { params });
    },
};
