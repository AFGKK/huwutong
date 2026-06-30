import client from './client';

export default {
    // 客户端（Portal）
    create(data) {
        return client.post('/handoff', data);
    },
    status(id) {
        return client.get(`/handoff/${id}`);
    },
    sendMessage(id, content) {
        return client.post(`/handoff/${id}/messages`, { content });
    },
    myHistory() {
        return client.get('/handoffs/my');
    },

    // 客服端（Admin）
    getQueue() {
        return client.get('/handoffs/queue');
    },
    myConversations() {
        return client.get('/handoff/conversations');
    },
    show(id) {
        return client.get(`/handoff/${id}`);
    },
    accept(id) {
        return client.post(`/handoff/${id}/accept`);
    },
    agentSend(id, content) {
        return client.post(`/handoff/${id}/messages`, { content });
    },
    close(id, note) {
        return client.post(`/handoff/${id}/close`, { note });
    },
    transfer(id, toAgentId, note) {
        return client.post(`/handoff/${id}/transfer`, { to_agent_id: toAgentId, note });
    },

    // ─── 新增：客服工作台所需 ───
    updateStatus(status) {
        return client.post('/handoff/status', { status });
    },
    getQueueStats() {
        return client.get('/handoff/queue-stats');
    },
    onlineAgents() {
        return client.get('/handoff/online-agents');
    },

    // ─── 访客信息 ───
    visitorInfo(id) {
        return client.get(`/handoff/${id}/visitor`);
    },
};
