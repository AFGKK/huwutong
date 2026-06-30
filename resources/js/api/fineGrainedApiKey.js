import apiClient from './client';

const fineGrainedApiKeyApi = {
    // 获取 SDK 端点元数据
    getSdkEndpoints() {
        return apiClient.get('/api-keys/fine-grained/sdk-endpoints');
    },

    // 获取指定 Key 的端点权限配置
    getKeyPermissions(id) {
        return apiClient.get(`/api-keys/${id}/permissions`);
    },

    // 更新 Key 的端点权限配置
    updatePermissions(id, data) {
        return apiClient.put(`/api-keys/${id}/permissions`, data);
    },

    // 获取 Key 的用量统计详情
    getKeyUsageStats(id) {
        return apiClient.get(`/api-keys/${id}/usage-stats/detail`);
    },
};

export default fineGrainedApiKeyApi;
