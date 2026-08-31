import apiClient from './client';

export function getAutoReplies(params = {}) {
    return apiClient.get('/user-chat/auto-reply', { params });
}

export function getAutoReplyStatus() {
    return apiClient.get('/user-chat/auto-reply/status');
}

export function createAutoReply(data) {
    return apiClient.post('/user-chat/auto-reply', data);
}

export function updateAutoReply(id, data) {
    return apiClient.put(`/user-chat/auto-reply/${id}`, data);
}

export function deleteAutoReply(id) {
    return apiClient.delete(`/user-chat/auto-reply/${id}`);
}
