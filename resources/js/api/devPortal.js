import apiClient from './client';

const devPortalApi = {
    dashboard() { return apiClient.get('/admin/dev-portal/dashboard'); },
    sdks() { return apiClient.get('/admin/dev-portal/sdks'); },
    quickstartSteps() { return apiClient.get('/admin/dev-portal/quickstart-steps'); },
    publicData() { return apiClient.get('/dev-portal/public'); },
};

export default devPortalApi;
