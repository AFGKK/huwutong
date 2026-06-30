import client from './client';

export default {
    // 客户门户端
    createConversation(data = {}) {
        return client.post('/live-chat/conversations', data);
    },
    sendMessage(conversationId, content) {
        return client.post(`/live-chat/conversations/${conversationId}/messages`, { content });
    },
    getMessages(conversationId) {
        return client.get(`/live-chat/conversations/${conversationId}/messages`);
    },
    closeConversation(conversationId, data = {}) {
        return client.post(`/live-chat/conversations/${conversationId}/close`, data);
    },

    // 管理端
    getDashboard() {
        return client.get('/live-chat/admin/dashboard');
    },
    listConversations(params = {}) {
        return client.get('/live-chat/admin/conversations', { params });
    },
    acceptHandoff(handoffId) {
        return client.post(`/live-chat/admin/handoffs/${handoffId}/accept`);
    },
    getPendingHandoffs() {
        return client.get('/live-chat/admin/pending-handoffs');
    },
};
