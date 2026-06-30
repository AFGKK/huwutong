import apiClient from './client'

export default {
  // 公众号列表/发现
  list(params) { return apiClient.get('/official-accounts', { params }) },
  search(params) { return apiClient.get('/official-accounts/search', { params }) },
  categories() { return apiClient.get('/official-accounts/categories') },

  // 关注/取消
  follow(id) { return apiClient.post(`/official-accounts/${id}/follow`) },
  unfollow(id) { return apiClient.post(`/official-accounts/${id}/unfollow`) },
  followers(id, params) { return apiClient.get(`/official-accounts/${id}/followers`, { params }) },

  // 我关注的/我拥有的
  myAccounts() { return apiClient.get('/official-accounts/my') },
  myOwnedAccounts() { return apiClient.get('/official-accounts/my-owned') },
  myFavoriteArticles() { return apiClient.get('/official-accounts/my-favorite-articles') },

  // 公众号管理
  create(data) { return apiClient.post('/official-accounts', data) },
  update(id, data) { return apiClient.put(`/official-accounts/${id}`, data) },
  editInfo(id) { return apiClient.get(`/official-accounts/${id}/edit-info`) },
  dashboard(id) { return apiClient.get(`/official-accounts/${id}/dashboard`) },
  uploadAvatar(data) { return apiClient.post('/official-accounts/upload-avatar', data) },

  // 文章
  articles(accountId, params) { return apiClient.get(`/official-accounts/${accountId}/articles`, { params }) },
  articleDetail(accountId, articleId) { return apiClient.get(`/official-accounts/${accountId}/articles/${articleId}`) },
  createArticle(accountId, data) { return apiClient.post(`/official-accounts/${accountId}/articles`, data) },
  updateArticle(accountId, articleId, data) { return apiClient.put(`/official-accounts/${accountId}/articles/${articleId}`, data) },
  deleteArticle(accountId, articleId) { return apiClient.delete(`/official-accounts/${accountId}/articles/${articleId}`) },
  toggleLike(accountId, articleId) { return apiClient.post(`/official-accounts/${accountId}/articles/${articleId}/like`) },
  articleStats(accountId, articleId) { return apiClient.get(`/official-accounts/${accountId}/articles/${articleId}/stats`) },

  // 评论
  comments(accountId, params) { return apiClient.get(`/official-accounts/${accountId}/comments`, { params }) },
  replyComment(commentId, data) { return apiClient.post(`/official-accounts/comments/${commentId}/reply`, data) },
  deleteComment(commentId) { return apiClient.delete(`/official-accounts/comments/${commentId}`) },
  approveComment(commentId) { return apiClient.post(`/official-accounts/comments/${commentId}/approve`) },
  rejectComment(commentId) { return apiClient.post(`/official-accounts/comments/${commentId}/reject`) },

  // 投稿
  submitArticle(accountId, data) { return apiClient.post(`/official-accounts/${accountId}/submissions`, data) },
  mySubmissions() { return apiClient.get('/official-accounts/submissions/my') },
  pendingSubmissions(accountId) { return apiClient.get(`/official-accounts/${accountId}/submissions/pending`) },
  reviewSubmission(submissionId, data) { return apiClient.post(`/official-accounts/submissions/${submissionId}/review`, data) },
}
