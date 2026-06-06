import apiClient from './client';

export default {
    manageAllFlags() {
        return apiClient.get('/openfeature/manage/flags');
    },
};
