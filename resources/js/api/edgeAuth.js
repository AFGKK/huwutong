import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/innovation/edge/dashboard');
    },

    // 边缘节点管理
    listNodes(params = {}) {
        return client.get('/admin/innovation/edge/nodes', { params });
    },
    registerNode(data) {
        return client.post('/admin/innovation/edge/nodes', data);
    },
};
