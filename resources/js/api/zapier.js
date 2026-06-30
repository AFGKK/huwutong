import apiClient from './client';

const BASE = '/admin/zapier';

export default {
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },
    getWorkflowTemplates() {
        return apiClient.get(`${BASE}/workflow-templates`).then(r => r.data);
    },
    getEmbedConfig() {
        return apiClient.get(`${BASE}/embed-config`).then(r => r.data);
    },
};
