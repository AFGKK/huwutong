import apiClient from './client';

export default {
    // 字段定义管理
    getDefinitions(params = {}) {
        return apiClient.get('/api/custom-fields', { params });
    },
    createDefinition(data) {
        return apiClient.post('/api/custom-fields', data);
    },
    updateDefinition(id, data) {
        return apiClient.put(`/api/custom-fields/${id}`, data);
    },
    deleteDefinition(id) {
        return apiClient.delete(`/api/custom-fields/${id}`);
    },

    // License 字段值
    getLicenseValues(licenseId) {
        return apiClient.get(`/api/custom-fields/licenses/${licenseId}/values`);
    },
    updateLicenseValues(licenseId, values) {
        return apiClient.put(`/api/custom-fields/licenses/${licenseId}/values`, { values });
    },

    // 客户字段值
    getCustomerValues(customerId) {
        return apiClient.get(`/api/custom-fields/customers/${customerId}/values`);
    },
    updateCustomerValues(customerId, values) {
        return apiClient.put(`/api/custom-fields/customers/${customerId}/values`, { values });
    },

    // 产品字段值
    getProductValues(productId) {
        return apiClient.get(`/api/custom-fields/products/${productId}/values`);
    },
    updateProductValues(productId, values) {
        return apiClient.put(`/api/custom-fields/products/${productId}/values`, { values });
    },

    // 元数据
    getMetadata() {
        return apiClient.get('/api/custom-fields/metadata');
    },
};
