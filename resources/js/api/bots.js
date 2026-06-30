import apiClient from './client'

export default {
  // 公开端点
  webhook(data) {
    return apiClient.post('/bots/webhook', data)
  },
  executeCommand(data) {
    return apiClient.post('/bots/execute', data)
  },

  // 需认证
  register(data) {
    return apiClient.post('/bots/register', data)
  },
  myBots() {
    return apiClient.get('/bots/my')
  },
  refreshToken(id) {
    return apiClient.post(`/bots/${id}/refresh-token`)
  },
  marketplace() {
    return apiClient.get('/bots/marketplace')
  },
}
