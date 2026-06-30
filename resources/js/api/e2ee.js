import apiClient from './client'

export default {
  registerKeys(data) {
    return apiClient.post('/e2ee/keys/register', data)
  },
  generateKeys() {
    return apiClient.get('/e2ee/keys/generate')
  },
  getPrekeyBundle(userId) {
    return apiClient.get(`/e2ee/keys/${userId}`)
  },
  initSession(data) {
    return apiClient.post('/e2ee/session/init', data)
  },
  encrypt(data) {
    return apiClient.post('/e2ee/encrypt', data)
  },
  decrypt(data) {
    return apiClient.post('/e2ee/decrypt', data)
  },
  status() {
    return apiClient.get('/e2ee/status')
  },
}
