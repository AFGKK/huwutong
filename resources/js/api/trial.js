import apiClient from './client';

const trialApi = {
    create(data) {
        return apiClient.post('/trial', data);
    },
    status(licenseId) {
        return apiClient.get(`/trial/${licenseId}`);
    },
    convert(licenseId, data) {
        return apiClient.post(`/trial/${licenseId}/convert`, data);
    },
};

export default trialApi;
