import apiClient from './client';

const BASE = '/chaos-engineering';

export default {
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },
    getExperiments(params) {
        return apiClient.get(BASE, { params }).then(r => r.data);
    },
    createExperiment(data) {
        return apiClient.post(BASE, data).then(r => r.data);
    },
    getExperiment(id) {
        return apiClient.get(`${BASE}/${id}`).then(r => r.data);
    },
    executeExperiment(id) {
        return apiClient.post(`${BASE}/${id}/execute`).then(r => r.data);
    },
    rollbackExperiment(id) {
        return apiClient.post(`${BASE}/${id}/rollback`).then(r => r.data);
    },
    deleteExperiment(id) {
        return apiClient.delete(`${BASE}/${id}`).then(r => r.data);
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
