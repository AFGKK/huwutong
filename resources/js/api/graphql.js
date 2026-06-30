import apiClient from '@/api/client';

export default {
    query(payload) {
        return apiClient.post('/admin/graphql', payload);
    },
    batch(queries) {
        return apiClient.post('/admin/graphql', { batch: queries });
    },
    schema() {
        return apiClient.get('/admin/graphql/schema');
    },
    explorerData() {
        return apiClient.get('/admin/graphql/explorer/data');
    },
};
