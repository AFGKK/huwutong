import apiClient from './client';

export default {
    activate(data) {
        return apiClient.post('/license/activate', data);
    },
    validate(data) {
        return apiClient.post('/license/validate', data);
    },
};
