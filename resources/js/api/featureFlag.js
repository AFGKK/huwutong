import apiClient from './client';

export default {
    list() {
        return apiClient.get('/feature-flags');
    },
    assign(data) {
        return apiClient.post('/feature-flags/assign', data);
    },
};
