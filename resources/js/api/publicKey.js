import apiClient from './client';

const publicKeyApi = {
    index(params) { return apiClient.get('/public-keys/versions', { params }); },
    show(keyVersion) { return apiClient.get(`/public-keys/versions/${keyVersion}`); },
    store(data) { return apiClient.post('/public-keys/versions', data); },
    revoke(keyVersion, data) { return apiClient.post(`/public-keys/versions/${keyVersion}/revoke`, data); },
    testSigning(data) { return apiClient.post('/public-keys/test-signing', data); },
    stats() { return apiClient.get('/public-keys/stats'); },
    rotationCheck() { return apiClient.get('/public-keys/rotation-check'); },
};

export default publicKeyApi;
