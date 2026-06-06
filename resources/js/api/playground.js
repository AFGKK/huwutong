import apiClient from './client';

export default {
    endpoints() {
        return apiClient.get('/playground/endpoints');
    },
    execute(data) {
        return apiClient.post('/playground/execute', data);
    },
    generateCode(data) {
        return apiClient.post('/playground/generate-code', data);
    },
};
