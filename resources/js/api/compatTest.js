import apiClient from './client';

export default {
    // 平台
    getPlatforms() {
        return apiClient.get('/admin/compat-test/platforms');
    },
    getPlatformTemplates() {
        return apiClient.get('/admin/compat-test/platforms/templates');
    },
    initializePlatforms(data = {}) {
        return apiClient.post('/admin/compat-test/platforms/init', data);
    },

    // 套件
    getSuites() {
        return apiClient.get('/admin/compat-test/suites');
    },
    createSuite(data) {
        return apiClient.post('/admin/compat-test/suites', data);
    },
    getSuiteDetail(id) {
        return apiClient.get(`/admin/compat-test/suites/${id}`);
    },

    // 用例
    addTestCase(suiteId, data) {
        return apiClient.post(`/admin/compat-test/suites/${suiteId}/cases`, data);
    },
    bulkAddTestCases(suiteId, data) {
        return apiClient.post(`/admin/compat-test/suites/${suiteId}/cases/bulk`, data);
    },

    // 运行
    createTestRun(data) {
        return apiClient.post('/admin/compat-test/runs', data);
    },
    startTestRun(id) {
        return apiClient.post(`/admin/compat-test/runs/${id}/start`);
    },
    recordResult(id, data) {
        return apiClient.post(`/admin/compat-test/runs/${id}/result`, data);
    },
    recordBatchResults(id, data) {
        return apiClient.post(`/admin/compat-test/runs/${id}/results/batch`, data);
    },
    completeTestRun(id) {
        return apiClient.post(`/admin/compat-test/runs/${id}/complete`);
    },
    getTestRunDetail(id) {
        return apiClient.get(`/admin/compat-test/runs/${id}`);
    },
    getTestRunHistory(params = {}) {
        return apiClient.get('/admin/compat-test/runs', { params });
    },

    // 统计
    getStats() {
        return apiClient.get('/admin/compat-test/stats');
    },
};
