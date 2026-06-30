import apiClient from './client';

const slowQueryMonitorApi = {
    dashboard(params = {}) {
        return apiClient.get('/slow-query/dashboard', { params });
    },
    topSlowQueries(params = {}) {
        return apiClient.get('/slow-query/top', { params });
    },
    list(params = {}) {
        return apiClient.get('/slow-query/list', { params });
    },
    show(id) {
        return apiClient.get(`/slow-query/${id}`);
    },
    explain(id) {
        return apiClient.get(`/slow-query/${id}/explain`);
    },
    resolve(id) {
        return apiClient.post(`/slow-query/${id}/resolve`);
    },
    batchResolve(ids) {
        return apiClient.post('/slow-query/batch-resolve', { ids });
    },
    byRoute(params = {}) {
        return apiClient.get('/slow-query/by-route', { params });
    },
    checkAlert() {
        return apiClient.get('/slow-query/check-alert');
    },
    prune() {
        return apiClient.post('/slow-query/prune');
    },
};

export default slowQueryMonitorApi;
