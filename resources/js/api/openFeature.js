import apiClient from './client';

export default {
    manageAllFlags() {
        return apiClient.get('/openfeature/manage/flags');
    },
    health() {
        return apiClient.get('/openfeature/health');
    },
    evaluate(data) {
        return apiClient.post('/openfeature/evaluate', data);
    },
};
