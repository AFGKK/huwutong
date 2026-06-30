import apiClient from './client';

export default {
    list() {
        return apiClient.get('/admin/custom-fields');
    },
    create(data) {
        return apiClient.post('/admin/custom-fields', data);
    },
    update(id, data) {
        return apiClient.put(`/admin/custom-fields/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/admin/custom-fields/${id}`);
    },
    licenseValues(licenseId) {
        return apiClient.get(`/admin/custom-fields/licenses/${licenseId}/values`);
    },
    updateLicenseValues(licenseId, values) {
        return apiClient.put(`/admin/custom-fields/licenses/${licenseId}/values`, { values });
    },
};
