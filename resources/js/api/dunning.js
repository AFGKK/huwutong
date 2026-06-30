import apiClient from './client';

export default {
    dashboard() {
        return apiClient.get('/dunning/dashboard');
    },
    queue(params = {}) {
        return apiClient.get('/dunning/queue', { params });
    },
    showQueue(id) {
        return apiClient.get(`/dunning/queue/${id}`);
    },
    resolve(id, status = 'resolved') {
        return apiClient.post(`/dunning/queue/${id}/resolve`, { status });
    },
    logs(params = {}) {
        return apiClient.get('/dunning/logs', { params });
    },
    strategies() {
        return apiClient.get('/dunning/strategies');
    },
    storeStrategy(data) {
        return apiClient.post('/dunning/strategies', data);
    },
    updateStrategy(id, data) {
        return apiClient.put(`/dunning/strategies/${id}`, data);
    },
    destroyStrategy(id) {
        return apiClient.delete(`/dunning/strategies/${id}`);
    },
    enqueue(data) {
        return apiClient.post('/dunning/enqueue', data);
    },
    run() {
        return apiClient.post('/dunning/run');
    },
    scanOverdue() {
        return apiClient.post('/dunning/scan-overdue');
    },
};
