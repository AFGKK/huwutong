import request from '@/utils/request';

export default {
    dashboard() {
        return request.get('/admin/workflows/dashboard');
    },
    definitions() {
        return request.get('/admin/workflows/definitions');
    },
    instances(params) {
        return request.get('/admin/workflows/instances', { params });
    },
    show(id) {
        return request.get(`/admin/workflows/${id}`);
    },
    progress(id) {
        return request.get(`/admin/workflows/${id}/progress`);
    },
    sagaStatus(id) {
        return request.get(`/admin/workflows/${id}/saga`);
    },
    cancel(id) {
        return request.post(`/admin/workflows/${id}/cancel`);
    },
    retry(id) {
        return request.post(`/admin/workflows/${id}/retry`);
    },
    batchRetry(data) {
        return request.post('/admin/workflows/batch-retry', data);
    },
    stats() {
        return request.get('/admin/workflows/stats');
    },
    startWorkflow(data) {
        return request.post('/admin/workflows/start', data);
    },
    failedSteps(params) {
        return request.get('/admin/workflows/failed-steps', { params });
    },
    config() {
        return request.get('/admin/workflows/config');
    },
};
