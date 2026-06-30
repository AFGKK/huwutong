import client from './client';

export default {
    getRules() {
        return client.get('/admin/mock-server/rules');
    },
    createRule(data) {
        return client.post('/admin/mock-server/rules', data);
    },
    updateRule(id, data) {
        return client.put(`/admin/mock-server/rules/${id}`, data);
    },
    deleteRule(id) {
        return client.delete(`/admin/mock-server/rules/${id}`);
    },
    importPrebuilt() {
        return client.post('/admin/mock-server/import');
    },
    getTemplates() {
        return client.get('/admin/mock-server/templates');
    },
    getConfig() {
        return client.get('/admin/mock-server/config');
    },
};
