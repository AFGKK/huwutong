import apiClient from './client';

const offlineApi = {
    generate(licenseId) {
        return apiClient.post('/offline/generate', { license_id: licenseId });
    },
    generateBatch(licenseIds) {
        return apiClient.post('/offline/generate/batch', { license_ids: licenseIds });
    },
    revoke(licenseKey, reason) {
        return apiClient.post('/offline/revoke', { license_key: licenseKey, reason });
    },
    restore(licenseKey) {
        return apiClient.post('/offline/restore', { license_key: licenseKey });
    },
    initKeys() {
        return apiClient.post('/offline/init-keys');
    },
    publicKey(keyVersion) {
        const params = keyVersion ? { key_version: keyVersion } : {};
        return apiClient.get('/offline/public-key', { params });
    },
};

export default offlineApi;
