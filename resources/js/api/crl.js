import client from './client';

export default {
    dashboard() {
        return client.get('/admin/crl/dashboard');
    },
    entries(params = {}) {
        return client.get('/admin/crl/entries', { params });
    },
    revoke(data) {
        return client.post('/admin/crl/revoke', data);
    },
    batchRevoke(data) {
        return client.post('/admin/crl/batch-revoke', data);
    },
    restore(data) {
        return client.post('/admin/crl/restore', data);
    },
    check(licenseKey) {
        return client.get(`/admin/crl/check/${licenseKey}`);
    },
    autoVerify(batch = 100) {
        return client.post('/admin/crl/auto-verify', { batch });
    },
};
