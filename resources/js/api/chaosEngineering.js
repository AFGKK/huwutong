import apiClient from './client';

const BASE = '/admin/chaos';

export default {
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },
    getExperiments(params) {
        return apiClient.get(`${BASE}/experiments`, { params }).then(r => r.data);
    },
    createExperiment(data) {
        return apiClient.post(`${BASE}/experiments`, data).then(r => r.data);
    },
    getExperiment(id) {
        return apiClient.get(`${BASE}/experiments/${id}`).then(r => r.data);
    },
    executeExperiment(id) {
        return apiClient.post(`${BASE}/experiments/${id}/execute`).then(r => r.data);
    },
    rollbackExperiment(id) {
        return apiClient.post(`${BASE}/experiments/${id}/rollback`).then(r => r.data);
    },
    deleteExperiment(id) {
        return apiClient.delete(`${BASE}/experiments/${id}`).then(r => r.data);
    },
    getScorecard() {
        return apiClient.get(`${BASE}/scorecard`).then(r => r.data);
    },
    getGameDay() {
        return apiClient.get(`${BASE}/gameday`).then(r => r.data);
    },
    getImprovements() {
        return apiClient.get(`${BASE}/improvements`).then(r => r.data);
    },
    getTypes() {
        return apiClient.get(`${BASE}/types`).then(r => r.data);
    },
};
