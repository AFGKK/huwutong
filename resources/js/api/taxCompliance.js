import apiClient from './client';

export default {
    dashboard() {
        return apiClient.get('/admin/tax/compliance/dashboard');
    },

    // Reports
    reports(params = {}) {
        return apiClient.get('/admin/tax/compliance/reports', { params });
    },
    generateReport(data) {
        return apiClient.post('/admin/tax/compliance/reports/generate', data);
    },
    fileReport(reportId) {
        return apiClient.post(`/admin/tax/compliance/reports/${reportId}/file`);
    },

    // Documents
    documents(params = {}) {
        return apiClient.get('/admin/tax/compliance/documents', { params });
    },
    storeDocument(data) {
        return apiClient.post('/admin/tax/compliance/documents', data);
    },
    updateDocument(documentId, data) {
        return apiClient.put(`/admin/tax/compliance/documents/${documentId}`, data);
    },
    deleteDocument(documentId) {
        return apiClient.delete(`/admin/tax/compliance/documents/${documentId}`);
    },

    // Rules
    rules(params = {}) {
        return apiClient.get('/admin/tax/compliance/rules', { params });
    },
    storeRule(data) {
        return apiClient.post('/admin/tax/compliance/rules', data);
    },
    updateRule(ruleId, data) {
        return apiClient.put(`/admin/tax/compliance/rules/${ruleId}`, data);
    },
    deleteRule(ruleId) {
        return apiClient.delete(`/admin/tax/compliance/rules/${ruleId}`);
    },
};
