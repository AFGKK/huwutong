import apiClient from './client';

export default {
    diagnose(data) {
        return apiClient.post('/diagnostic/diagnose', data);
    },
    diagnoseActivation(data) {
        return apiClient.post('/diagnostic/activation', data);
    },
    diagnoseBatch(data) {
        return apiClient.post('/diagnostic/batch', data);
    },
    sdkSuggestions() {
        return apiClient.get('/diagnostic/sdk-suggestions');
    },
};
