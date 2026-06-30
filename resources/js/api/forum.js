import apiClient from './client'

export default {
  list(params) { return apiClient.get('/forum', { params }) },
  create(data) { return apiClient.post('/forum', data) },
  show(id) { return apiClient.get(`/forum/${id}`) },
  destroy(id) { return apiClient.delete(`/forum/${id}`) },
  categories() { return apiClient.get('/forum/categories') },
  reply(id, data) { return apiClient.post(`/forum/${id}/reply`, data) },
  toggleLike(id) { return apiClient.post(`/forum/${id}/like`) },
}
