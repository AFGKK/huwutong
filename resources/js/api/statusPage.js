import client from './client';

export default {
    // 公开端点
    getStatus() {
        return client.get('/status');
    },
    getHistory(params) {
        return client.get('/status/history', { params });
    },
    subscribe(email) {
        return client.post('/status/subscribe', { email });
    },

    // 管理端点 - 组件
    getComponents() {
        return client.get('/status/components');
    },
    createComponent(data) {
        return client.post('/status/components', data);
    },
    updateComponent(id, data) {
        return client.put(`/status/components/${id}`, data);
    },
    deleteComponent(id) {
        return client.delete(`/status/components/${id}`);
    },

    // 管理端点 - 事件
    getIncidents(params) {
        return client.get('/status/incidents', { params });
    },
    getIncident(id) {
        return client.get(`/status/incidents/${id}`);
    },
    createIncident(data) {
        return client.post('/status/incidents', data);
    },
    updateIncidentStatus(id, data) {
        return client.post(`/status/incidents/${id}/update`, data);
    },
    deleteIncident(id) {
        return client.delete(`/status/incidents/${id}`);
    },

    // 订阅者
    getSubscribers(params) {
        return client.get('/status/subscribers', { params });
    },

    // 检查
    runChecks() {
        return client.post('/status/checks');
    },
    getStats() {
        return client.get('/status/stats');
    },
};
