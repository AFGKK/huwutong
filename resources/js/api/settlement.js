import apiClient from './client';

export default {
    // 仪表盘
    dashboard() {
        return apiClient.get('/settlement/dashboard');
    },

    // 结算周期
    cycles(params) {
        return apiClient.get('/settlement/cycles', { params });
    },
    cycleShow(id) {
        return apiClient.get(`/settlement/cycles/${id}`);
    },
    cycleCreate(data) {
        return apiClient.post('/settlement/cycles', data);
    },
    cycleGenerate() {
        return apiClient.post('/settlement/cycles/generate');
    },

    // 可结算佣金扫描
    scanReleasable() {
        return apiClient.get('/settlement/releasable');
    },

    // 结算批次
    batches(params) {
        return apiClient.get('/settlement/batches', { params });
    },
    batchShow(id) {
        return apiClient.get(`/settlement/batches/${id}`);
    },
    batchCreate(data) {
        return apiClient.post('/settlement/batches', data);
    },
    batchSubmit(id) {
        return apiClient.post(`/settlement/batches/${id}/submit`);
    },
    batchApprove(id) {
        return apiClient.post(`/settlement/batches/${id}/approve`);
    },
    batchComplete(id) {
        return apiClient.post(`/settlement/batches/${id}/complete`);
    },
    batchCancel(id) {
        return apiClient.post(`/settlement/batches/${id}/cancel`);
    },

    // 平台费用
    feeStats(params) {
        return apiClient.get('/settlement/fees', { params });
    },
};
