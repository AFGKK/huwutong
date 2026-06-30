import apiClient from './client';

const BASE = '/admin/blue-green';

export default {
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },
    getHistory() {
        return apiClient.get(`${BASE}/history`).then(r => r.data);
    },
    startDeployment(releaseId, notes) {
        return apiClient.post(`${BASE}/start`, { release_id: releaseId, notes }).then(r => r.data);
    },
    healthCheck(id) {
        return apiClient.post(`${BASE}/deployments/${id}/health-check`).then(r => r.data);
    },
    verify(id) {
        return apiClient.post(`${BASE}/deployments/${id}/verify`).then(r => r.data);
    },
    switchTraffic(id) {
        return apiClient.post(`${BASE}/deployments/${id}/switch`).then(r => r.data);
    },
    rollback(id, reason) {
        return apiClient.post(`${BASE}/deployments/${id}/rollback`, { reason }).then(r => r.data);
    },
    showDeployment(id) {
        return apiClient.get(`${BASE}/deployments/${id}`).then(r => r.data);
    },
};
