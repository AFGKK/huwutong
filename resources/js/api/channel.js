import apiClient from './client'

export default {
  // 频道 CRUD
  list(params) { return apiClient.get('/channels', { params }) },
  create(data) { return apiClient.post('/channels', data) },
  show(id) { return apiClient.get(`/channels/${id}`) },
  update(id, data) { return apiClient.put(`/channels/${id}`, data) },
  destroy(id) { return apiClient.delete(`/channels/${id}`) },

  // 浏览/发现
  browse(params) { return apiClient.get('/channels/browse', { params }) },

  // 分类
  categories() { return apiClient.get('/channels/categories') },
  createCategory(data) { return apiClient.post('/channels/categories', data) },
  updateCategory(id, data) { return apiClient.put(`/channels/categories/${id}`, data) },
  deleteCategory(id) { return apiClient.delete(`/channels/categories/${id}`) },

  // 成员
  join(id) { return apiClient.post(`/channels/${id}/join`) },
  leave(id) { return apiClient.post(`/channels/${id}/leave`) },
  members(id) { return apiClient.get(`/channels/${id}/members`) },
  updateMemberRole(channelId, memberId, role) {
    return apiClient.put(`/channels/${channelId}/members/${memberId}/role`, { role })
  },
  transferOwnership(channelId, newOwnerId) {
    return apiClient.post(`/channels/${channelId}/transfer`, { new_owner_id: newOwnerId })
  },

  // 消息
  messages(channelId, params) { return apiClient.get(`/channels/${channelId}/messages`, { params }) },
  sendMessage(channelId, data) { return apiClient.post(`/channels/${channelId}/messages`, data) },
  deleteMessage(channelId, msgId) { return apiClient.delete(`/channels/${channelId}/messages/${msgId}`) },
  recallMessage(channelId, msgId) { return apiClient.post(`/channels/${channelId}/messages/${msgId}/recall`) },
  searchMessages(channelId, params) { return apiClient.get(`/channels/${channelId}/messages/search`, { params }) },

  // 置顶
  pinMessage(channelId, msgId) { return apiClient.post(`/channels/${channelId}/messages/${msgId}/pin`) },
  unpinMessage(channelId, msgId) { return apiClient.post(`/channels/${channelId}/messages/${msgId}/unpin`) },
  pinnedMessages(channelId) { return apiClient.get(`/channels/${channelId}/pinned-messages`) },

  // 通知
  toggleMute(channelId) { return apiClient.post(`/channels/${channelId}/toggle-mute`) },

  // 头像
  uploadAvatar(data) { return apiClient.post('/channels/upload-avatar', data) },
}
