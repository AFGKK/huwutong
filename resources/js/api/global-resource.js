import apiClient from './client';

export default {
    config() {
        return apiClient.get('/global-resources/config');
    },
    checkWrite() {
        return apiClient.get('/global-resources/check-write');
    },
    verifyAccess(modelClass, action) {
        return apiClient.post('/global-resources/verify-access', { model_class: modelClass, action });
    },
    operations(params = {}) {
        return apiClient.get('/global-resources/operations', { params });
    },
};
