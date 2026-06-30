import client from './client';

export default {
    dashboard() {
        return client.get('/admin/flash-sale/dashboard');
    },
    list(params = {}) {
        return client.get('/admin/flash-sale/list', { params });
    },
    create(data) {
        return client.post('/admin/flash-sale/create', data);
    },
    updateStatus(id, status) {
        return client.post(`/admin/flash-sale/${id}/status`, { status });
    },
    releaseExpired(id) {
        return client.post(`/admin/flash-sale/${id}/release-expired`);
    },
};
