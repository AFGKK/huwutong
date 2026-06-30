import apiClient from './client';

export default {
    getConfig() {
        return apiClient.get('/admin/lark/config').then(r => r.data);
    },
    saveConfig(data) {
        return apiClient.post('/admin/lark/config', data).then(r => r.data);
    },
    testConnection() {
        return apiClient.post('/admin/lark/test').then(r => r.data);
    },
    sendTestMessage(data) {
        return apiClient.post('/admin/lark/test-message', data).then(r => r.data);
    },
    getReference() {
        return apiClient.get('/admin/lark/reference').then(r => r.data);
    },
};
