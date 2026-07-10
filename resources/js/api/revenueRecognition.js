import apiClient from './client';

export default {
    getSchedules(params = {}) {
        return apiClient.get('/revenue/schedules', { params });
    },
    getSchedule(id) {
        return apiClient.get(`/revenue/schedules/${id}`);
    },
    processRecognition(data = {}) {
        return apiClient.post('/revenue/process', data);
    },
    createSchedules(data = {}) {
        return apiClient.post('/revenue/create-schedules', data);
    },
    getSummary() {
        return apiClient.get('/revenue/summary');
    },
    getAsc606Report(params) {
        return apiClient.get('/revenue/asc606-report', { params });
    },
    getMonthlySnapshots(params = {}) {
        return apiClient.get('/revenue/monthly-snapshots', { params });
    },
    generateSnapshot(data = {}) {
        return apiClient.post('/revenue/generate-snapshot', data);
    },

    // M3-55 增强
    cancelSchedule(id, data = {}) {
        return apiClient.post(`/revenue/schedules/${id}/cancel`, data);
    },
    recomputeSchedule(id) {
        return apiClient.post(`/revenue/schedules/${id}/recompute`);
    },
    exportAsc606Report(params) {
        return apiClient.get('/revenue/asc606-report/export', { params, responseType: 'blob' });
    },

    // MRR 瀑布图 (M3-59)
    getMrrWaterfall(params = {}) {
        return apiClient.get('/revenue/mrr-waterfall', { params });
    },
    getMrrDrilldown(params = {}) {
        return apiClient.get('/revenue/mrr-drilldown', { params });
    },
    getMrrSummary(params = {}) {
        return apiClient.get('/revenue/mrr-summary', { params });
    },
};
