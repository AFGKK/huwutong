import client from './client';

export default {
    dashboard() {
        return client.get('/admin/secret-scan/dashboard');
    },
    entries(params = {}) {
        return client.get('/admin/secret-scan/entries', { params });
    },
    scan() {
        return client.post('/admin/secret-scan/scan');
    },
    quickScan() {
        return client.post('/admin/secret-scan/quick-scan');
    },
    resolve(id, data) {
        return client.post(`/admin/secret-scan/${id}/resolve`, data);
    },
};

