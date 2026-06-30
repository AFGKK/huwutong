import apiClient from './client';

const postmanApi = {
    downloadCollection() { return apiClient.get('/admin/postman/collection'); },
    downloadEnvironment(envName) { return apiClient.get(`/admin/postman/environment/${envName}`); },
    environments() { return apiClient.get('/admin/postman/environments'); },
    runInPostman() { return apiClient.get('/admin/postman/run-in-postman'); },
    stats() { return apiClient.get('/admin/postman/stats'); },
};

export default postmanApi;
