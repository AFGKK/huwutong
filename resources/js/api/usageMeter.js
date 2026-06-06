import apiClient from './client';

export default {
    // 获取可用计量指标
    getMetrics() {
        return apiClient.get('/usage/metrics');
    },

    // 记录单条用量
    record(data) {
        return apiClient.post('/usage/record', data);
    },

    // 批量记录用量
    recordBatch(records) {
        return apiClient.post('/usage/record-batch', { records });
    },

    // 检查配额
    checkQuota(data) {
        return apiClient.post('/usage/check-quota', data);
    },

    // 获取统计
    getStats(params) {
        return apiClient.get('/usage/stats', { params });
    },

    // 获取当前窗口用量
    getCurrentUsage(params) {
        return apiClient.get('/usage/current', { params });
    },

    // 获取用量总览
    getOverview() {
        return apiClient.get('/usage/overview');
    },

    // 配额管理 - 列表
    getQuotas(params) {
        return apiClient.get('/usage/quotas', { params });
    },

    // 配额管理 - 创建/更新
    upsertQuota(data) {
        return apiClient.post('/usage/quotas', data);
    },

    // 配额管理 - 删除
    deleteQuota(id) {
        return apiClient.delete(`/usage/quotas/${id}`);
    },
};
