import apiClient from './client'

export default {
  list(params) { return apiClient.get('/moments', { params }) },
  create(data) { return apiClient.post('/moments', data) },
  myPosts(params) { return apiClient.get('/moments/my', { params }) },
  categories() { return apiClient.get('/moments/categories') },
  uploadImage(data) { return apiClient.post('/moments/upload', data) },
  uploadVideo(data) { return apiClient.post('/moments/upload-video', data) },
  update(id, data) { return apiClient.put(`/moments/${id}`, data) },
  destroy(id) { return apiClient.delete(`/moments/${id}`) },

  // 互动
  toggleLike(id) { return apiClient.post(`/moments/${id}/like`) },
  toggleFavorite(id) { return apiClient.post(`/moments/${id}/favorite`) },
  forward(id) { return apiClient.post(`/moments/${id}/forward`) },

  // 评论
  comments(id) { return apiClient.get(`/moments/${id}/comments`) },
  comment(id, data) { return apiClient.post(`/moments/${id}/comment`, data) },
  replyComment(commentId, data) { return apiClient.post(`/moments/comments/${commentId}/reply`, data) },
  deleteComment(commentId) { return apiClient.delete(`/moments/comments/${commentId}`) },
}
