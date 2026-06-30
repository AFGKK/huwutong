import apiClient from './client';

export default {
    // 快捷回复
    getCannedReplies(params) { return apiClient.get('/im/canned-replies', { params }); },
    createCannedReply(data) { return apiClient.post('/im/canned-replies', data); },
    updateCannedReply(id, data) { return apiClient.put(`/im/canned-replies/${id}`, data); },
    deleteCannedReply(id) { return apiClient.delete(`/im/canned-replies/${id}`); },

    // 会话标签
    getTags() { return apiClient.get('/im/tags'); },
    createTag(data) { return apiClient.post('/im/tags', data); },
    updateTag(id, data) { return apiClient.put(`/im/tags/${id}`, data); },
    deleteTag(id) { return apiClient.delete(`/im/tags/${id}`); },
    assignTags(data) { return apiClient.post('/im/tags/assign', data); },
    getAssignedTags(data) { return apiClient.post('/im/tags/get-assigned', data); },

    // 客服组
    getGroups() { return apiClient.get('/im/groups'); },
    createGroup(data) { return apiClient.post('/im/groups', data); },
    updateGroup(id, data) { return apiClient.put(`/im/groups/${id}`, data); },
    deleteGroup(id) { return apiClient.delete(`/im/groups/${id}`); },
    addGroupMember(data) { return apiClient.post('/im/groups/add-member', data); },
    removeGroupMember(groupId, userId) { return apiClient.delete(`/im/groups/${groupId}/members/${userId}`); },

    // 自动回复规则
    getAutoReplyRules() { return apiClient.get('/im/auto-reply-rules'); },
    createAutoReplyRule(data) { return apiClient.post('/im/auto-reply-rules', data); },
    updateAutoReplyRule(id, data) { return apiClient.put(`/im/auto-reply-rules/${id}`, data); },
    deleteAutoReplyRule(id) { return apiClient.delete(`/im/auto-reply-rules/${id}`); },

    // 导出/上传
    exportConversation(id) { return apiClient.get(`/im/conversations/${id}/export`, { responseType: 'blob' }); },
    uploadChatFile(formData) { return apiClient.post('/im/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }); },

    // 绩效看板
    getAgentPerformance(params) { return apiClient.get('/im/agent-performance', { params }); },
    getImDashboard() { return apiClient.get('/im/dashboard'); },

    // 消息已读/置顶/免打扰/搜索
    markAsRead(data) { return apiClient.post('/im/messages/mark-read', data); },
    togglePin(id) { return apiClient.post(`/im/conversations/${id}/pin`); },
    toggleMute(id) { return apiClient.post(`/im/conversations/${id}/mute`); },
    deleteConversation(id) { return apiClient.delete(`/im/conversations/${id}`); },
    restoreConversation(id) { return apiClient.post(`/im/conversations/${id}/restore`); },
    saveDraft(id, content) { return apiClient.put(`/im/conversations/${id}/draft`, { draft_content: content }); },
    searchMessages(params) { return apiClient.get('/im/messages/search', { params }); },
    getUnreadConversations() { return apiClient.get('/im/conversations/unread'); },
    getNotifyConfig() { return apiClient.get('/im/notify-config'); },
};
