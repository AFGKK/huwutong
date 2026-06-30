import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/mlops/dashboard');
    },

    // 模型管理
    listModels(params = {}) {
        return client.get('/admin/mlops/models', { params });
    },
    getModel(id) {
        return client.get(`/admin/mlops/models/${id}`);
    },
    createModel(data) {
        return client.post('/admin/mlops/models', data);
    },
    updateModel(id, data) {
        return client.put(`/admin/mlops/models/${id}`, data);
    },
    deleteModel(id) {
        return client.delete(`/admin/mlops/models/${id}`);
    },

    // 版本管理
    listVersions(modelId, params = {}) {
        return client.get(`/admin/mlops/models/${modelId}/versions`, { params });
    },
    createVersion(modelId, data) {
        return client.post(`/admin/mlops/models/${modelId}/versions`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    deployVersion(modelId, versionId) {
        return client.post(`/admin/mlops/models/${modelId}/versions/${versionId}/deploy`);
    },
    rollbackVersion(modelId, versionId) {
        return client.post(`/admin/mlops/models/${modelId}/rollback/${versionId}`);
    },

    // 训练任务
    listTrainingJobs(params = {}) {
        return client.get('/admin/mlops/training-jobs', { params });
    },
    submitTraining(modelId, data = {}) {
        return client.post(`/admin/mlops/models/${modelId}/train`, data);
    },

    // 漂移监控
    listDriftEvents(params = {}) {
        return client.get('/admin/mlops/drift-events', { params });
    },
    getDriftSummary(params = {}) {
        return client.get('/admin/mlops/drift-summary', { params });
    },
    detectDrift(modelId, metrics) {
        return client.post(`/admin/mlops/models/${modelId}/detect-drift`, { metrics });
    },
};
