import apiClient from './client';

const licenseTrash = {
    list(params) {
        return apiClient.get('/license-trash', { params });
    },
    stats() {
        return apiClient.get('/license-trash/stats');
    },
    restore(id) {
        return apiClient.post(`/license-trash/${id}/restore`);
    },
    batchRestore(ids) {
        return apiClient.post('/license-trash/batch-restore', { ids });
    },
    forceDelete(id) {
        return apiClient.delete(`/license-trash/${id}/force`);
    },
    clear() {
        return apiClient.delete('/license-trash/clear');
    },
};

export default licenseTrash;
