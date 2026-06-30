import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/innovation/serverless/dashboard');
    },

    // 云函数管理
    listFunctions(params = {}) {
        return client.get('/admin/innovation/serverless/functions', { params });
    },
    registerFunction(data) {
        return client.post('/admin/innovation/serverless/functions', data);
    },
    generateToken(functionId) {
        return client.post(`/admin/innovation/serverless/functions/${functionId}/token`);
    },
};
