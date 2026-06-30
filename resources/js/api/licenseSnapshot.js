import apiClient from './client';

const licenseSnapshot = {
    list(params) {
        return apiClient.get('/license-snapshots', { params });
    },
    dashboard() {
        return apiClient.get('/license-snapshots/dashboard');
    },
    create(data) {
        return apiClient.post('/license-snapshots', data);
    },
    show(id) {
        return apiClient.get(`/license-snapshots/${id}`);
    },
    rollback(id) {
        return apiClient.post(`/license-snapshots/${id}/rollback`);
    },
};

export default licenseSnapshot;
