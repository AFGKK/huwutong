import apiClient from './client';

const publicKeyApi = {
    index(params) { return apiClient.get('/api/public-keys/versions', { params }); },
    show(keyVersion) { return apiClient.get(`/api/public-keys/versions/${keyVersion}`); },
    store(data) { return apiClient.post('/api/public-keys/versions', data); },
    revoke(keyVersion, data) { return apiClient.post(`/api/public-keys/versions/${keyVersion}/revoke`, data); },
    testSigning(data) { return apiClient.post('/api/public-keys/test-signing', data); },
    stats() { return apiClient.get('/api/public-keys/stats'); },
    rotationCheck() { return apiClient.get('/api/public-keys/rotation-check'); },
};

export default publicKeyApi;
