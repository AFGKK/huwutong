import request from '@/utils/request';

export default {
    checkDeletability() {
        return request.get('/account/deletion/check');
    },

    requestDeletion(data) {
        return request.post('/account/deletion', data);
    },

    getCancellationReasons() {
        return request.get('/account/deletion/reasons');
    },

    getDeletionRecords(params) {
        return request.get('/admin/deletion/records', { params });
    },

    getStats() {
        return request.get('/admin/deletion/stats');
    },

    adminAnonymize(data) {
        return request.post('/admin/deletion/admin/anonymize', data);
    },
};
