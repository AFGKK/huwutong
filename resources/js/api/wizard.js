import apiClient from './client';

export default {
    languages() {
        return apiClient.get('/wizard/languages');
    },
    products() {
        return apiClient.get('/wizard/products');
    },
    generateConfig(data) {
        return apiClient.post('/wizard/generate-config', data);
    },
    testConnectivity(data) {
        return apiClient.post('/wizard/test-connectivity', data);
    },
};
