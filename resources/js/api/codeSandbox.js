import apiClient from './client'

export default {
  execute(data) {
    return apiClient.post('/code-sandbox/execute', data)
  },
  getLanguages() {
    return apiClient.get('/code-sandbox/languages')
  },
  getTemplates() {
    return apiClient.get('/code-sandbox/templates')
  },
}
