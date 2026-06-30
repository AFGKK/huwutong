import apiClient from './client';

const BASE = '/local-llm';

export default {
    getStatus() {
        return apiClient.get(`${BASE}/status`).then(r => r.data);
    },
    getGpuInfo() {
        return apiClient.get(`${BASE}/gpu`).then(r => r.data);
    },
    getHardwareInfo() {
        return apiClient.get(`${BASE}/hardware`).then(r => r.data);
    },
    getDeploymentGuide() {
        return apiClient.get(`${BASE}/deployment-guide`).then(r => r.data);
    },
    pullModel(modelName) {
        return apiClient.post(`${BASE}/models/pull`, { model_name: modelName }).then(r => r.data);
    },
    deleteModel(modelName) {
        return apiClient.delete(`${BASE}/models/${modelName}`).then(r => r.data);
    },
    checkInstance(providerId) {
        return apiClient.get(`${BASE}/instances/${providerId}/check`).then(r => r.data);
    },
};
