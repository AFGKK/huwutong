import apiClient from './client'

export default {
  // IM 管理后台
  users(params) { return apiClient.get('/im-admin/users', { params }) },
  userDetail(id) { return apiClient.get(`/im-admin/users/${id}`) },
  groups(params) { return apiClient.get('/im-admin/groups', { params }) },
  groupDetail(id) { return apiClient.get(`/im-admin/groups/${id}`) },
  dismissGroup(id) { return apiClient.delete(`/im-admin/groups/${id}`) },
  messages(params) { return apiClient.get('/im-admin/messages', { params }) },
  deleteMessage(id) { return apiClient.delete(`/im-admin/messages/${id}`) },
  dashboard() { return apiClient.get('/im-admin/dashboard') },
  reports(params) { return apiClient.get('/im-admin/reports', { params }) },
  resolveReport(id) { return apiClient.post(`/im-admin/reports/${id}/resolve`) },
  conversations(params) { return apiClient.get('/im-admin/conversations', { params }) },
  conversationDetail(id) { return apiClient.get(`/im-admin/conversations/${id}`) },
  deleteConversation(id) { return apiClient.delete(`/im-admin/conversations/${id}`) },
  banUser(id) { return apiClient.post(`/im-admin/users/${id}/ban`) },
  unbanUser(id) { return apiClient.post(`/im-admin/users/${id}/unban`) },
}
