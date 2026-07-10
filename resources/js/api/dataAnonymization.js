import apiClient from './client';

const dataAnonymizationApi = {
    // 导出流水线
    export(data) { return apiClient.post('/data-anonymization/export', data); },
    preview(params) { return apiClient.post('/data-anonymization/preview', params); },
    tables() { return apiClient.get('/data-anonymization/tables'); },

    // 任务管理
    tasks(params) { return apiClient.get('/data-anonymization/tasks', { params }); },
    showTask(id) { return apiClient.get(`/data-anonymization/tasks/${id}`); },
    retryTask(id) { return apiClient.post(`/data-anonymization/tasks/${id}/retry`); },

    // 规则管理
    rules(params) { return apiClient.get('/data-anonymization/rules', { params }); },
    storeRule(data) { return apiClient.post('/data-anonymization/rules', data); },
    destroyRule(id) { return apiClient.delete(`/data-anonymization/rules/${id}`); },
};

export default dataAnonymizationApi;
