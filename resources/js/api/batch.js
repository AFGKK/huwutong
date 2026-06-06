import client from './client';

export default {
    getOperationTypes() {
        return client.get('/batch/operation-types');
    },
    preview(data) {
        return client.post('/batch/preview', data);
    },
    execute(data) {
        return client.post('/batch/execute', data);
    },
    getJobs(params = {}) {
        return client.get('/batch/jobs', { params });
    },
    getJob(id) {
        return client.get(`/batch/jobs/${id}`);
    },
    undo(id) {
        return client.post(`/batch/jobs/${id}/undo`);
    },
    export(id, format = 'csv') {
        return client.post(`/batch/jobs/${id}/export`, { format });
    },
};
