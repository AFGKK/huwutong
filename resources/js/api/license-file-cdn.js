import apiClient from './client';

export default {
    index(params = {}) {
        return apiClient.get('/license-files', { params });
    },
    generate(licenseId) {
        return apiClient.post('/license-files/generate', { license_id: licenseId });
    },
    batchGenerate(licenseIds) {
        return apiClient.post('/license-files/batch-generate', { license_ids: licenseIds });
    },
    revoke(licenseKey, reason = '') {
        return apiClient.post('/license-files/revoke', { license_key: licenseKey, reason });
    },
    redistribute(licenseId) {
        return apiClient.post('/license-files/redistribute', { license_id: licenseId });
    },
    rotateKey(publicKey, algorithm = 'Ed25519') {
        return apiClient.post('/license-files/rotate-key', { public_key: publicKey, algorithm });
    },
    stats() {
        return apiClient.get('/license-files/stats');
    },
    logs(params = {}) {
        return apiClient.get('/license-files/logs', { params });
    },
};
