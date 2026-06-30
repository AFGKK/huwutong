import apiClient from './client';

export default {
    getDashboard() {
        return apiClient.get('/local-proxy/dashboard');
    },
    getNodes() {
        return apiClient.get('/local-proxy/nodes');
    },
    registerNode(data) {
        return apiClient.post('/local-proxy/nodes/register', data);
    },
    activateNode(data) {
        return apiClient.post('/local-proxy/nodes/activate', data);
    },
    getNodeDetail(id) {
        return apiClient.get(`/local-proxy/nodes/${id}`);
    },
    updateNodeStatus(id, status) {
        return apiClient.put(`/local-proxy/nodes/${id}/status`, { status });
    },
    updateNodeConfig(id, data) {
        return apiClient.put(`/local-proxy/nodes/${id}/config`, data);
    },
};
