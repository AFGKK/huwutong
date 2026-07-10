import apiClient from './client';

const BASE = '/admin/custom-fields';

export default {
    getDefinitions(params = {}) {
        return apiClient.get(BASE, { params });
    },
    createDefinition(data) {
        return apiClient.post(BASE, data);
    },
    updateDefinition(id, data) {
        return apiClient.put(`${BASE}/${id}`, data);
    },
    deleteDefinition(id) {
        return apiClient.delete(`${BASE}/${id}`);
    },

    getLicenseValues(licenseId) {
        return apiClient.get(`${BASE}/licenses/${licenseId}/values`);
    },
    updateLicenseValues(licenseId, values) {
        return apiClient.put(`${BASE}/licenses/${licenseId}/values`, { values });
    },

    getCustomerValues(customerId) {
        return apiClient.get(`${BASE}/customers/${customerId}/values`);
    },
    updateCustomerValues(customerId, values) {
        return apiClient.put(`${BASE}/customers/${customerId}/values`, { values });
    },

    getProductValues(productId) {
        return apiClient.get(`${BASE}/products/${productId}/values`);
    },
    updateProductValues(productId, values) {
        return apiClient.put(`${BASE}/products/${productId}/values`, { values });
    },

    getMetadata() {
        return apiClient.get(`${BASE}/metadata`);
    },
};
