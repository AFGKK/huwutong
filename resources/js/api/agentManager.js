import client from './client';

export default {
    dashboard() {
        return client.get('/agent-manager/dashboard');
    },
    list(params = {}) {
        return client.get('/agent-manager/agents', { params });
    },
    show(id) {
        return client.get(`/agent-manager/agents/${id}`);
    },
    create(data) {
        return client.post('/agent-manager/agents', data);
    },
    update(id, data) {
        return client.put(`/agent-manager/agents/${id}`, data);
    },
    approve(id) {
        return client.post(`/agent-manager/agents/${id}/approve`);
    },
    performance(id, period = 'monthly') {
        return client.get(`/agent-manager/agents/${id}/performance`, { params: { period } });
    },
    leaderboard(params = {}) {
        return client.get('/agent-manager/leaderboard', { params });
    },
};
