import request from '@/utils/request';

const BASE = '/api/admin/deletion';

export default {
    // 用户端
    checkDeletability() {
        return request.get('/api/account/deletion/check');
    },

    requestDeletion(data) {
        return request.post('/api/account/deletion', data);
    },

    getCancellationReasons() {
        return request.get('/api/account/deletion/reasons');
    },

    // 管理端
    getDeletionRecords(params) {
        return request.get(`${BASE}/records`, { params });
    },

    getStats() {
        return request.get(`${BASE}/stats`);
    },

    adminAnonymize(data) {
        return request.post(`${BASE}/admin/anonymize`, data);
    },
};
