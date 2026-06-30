import apiClient from './client';

export default {
    preview(data) {
        return apiClient.post('/license-merge/preview', data);
    },
    execute(data) {
        return apiClient.post('/license-merge/execute', data);
    },
    getHistory(params) {
        return apiClient.get('/license-merge/history', { params });
    },
    getDetail(id) {
        return apiClient.get(`/license-merge/history/${id}`);
    },
    rollback(id) {
        return apiClient.post(`/license-merge/history/${id}/rollback`);
    },
    searchCustomers(keyword) {
        return apiClient.get('/license-merge/search-customers', { params: { keyword } });
    },
};
