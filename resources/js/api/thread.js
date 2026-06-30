import apiClient from './client'

export default {
  reply(messageId, content) {
    return apiClient.post(`/threads/messages/${messageId}/reply`, { content })
  },
  getReplies(messageId) {
    return apiClient.get(`/threads/messages/${messageId}/replies`)
  },
  getSummary(messageId) {
    return apiClient.get(`/threads/messages/${messageId}/summary`)
  },
}
