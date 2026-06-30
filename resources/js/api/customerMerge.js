import apiClient from './client';

export default {
    previewMerge(data) {
        return apiClient.post('/customer-merge/preview', data);
    },
    executeMerge(data) {
        return apiClient.post('/customer-merge/execute', data);
    },
    getHistory(params) {
        return apiClient.get('/customer-merge/history', { params });
    },
    getDetail(logId) {
        return apiClient.get(`/customer-merge/history/${logId}`);
    },
    searchCustomers(params) {
        return apiClient.get('/customer-merge/search-customers', { params });
    },
};
