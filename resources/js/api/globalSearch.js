import apiClient from './client';

export default {
  // 统一搜索
  search(params) {
    return apiClient.get('/admin/search', { params });
  },

  // 搜索建议
  getSuggestions(q, limit = 8) {
    return apiClient.get('/admin/search/suggestions', { params: { q, limit } });
  },

  getEngineStatus() {
    return apiClient.get('/admin/search/engine-status');
  },

  // 索引管理
  rebuildIndex(type = 'all') {
    return apiClient.post('/admin/search/rebuild', { type });
  },
  getIndexStatus() {
    return apiClient.get('/admin/search/index-status');
  },

  // 最近搜索
  getRecent() {
    return apiClient.get('/admin/search/recent');
  },
  clearRecent() {
    return apiClient.delete('/admin/search/recent');
  },
  deleteRecent(id) {
    return apiClient.delete(`/admin/search/recent/${id}`);
  },

  // 收藏
  getBookmarks() {
    return apiClient.get('/admin/search/bookmarks');
  },
  toggleBookmark(data) {
    return apiClient.post('/admin/search/bookmarks/toggle', data);
  },
  deleteBookmark(id) {
    return apiClient.delete(`/admin/search/bookmarks/${id}`);
  },

  // 偏好
  getPreferences() {
    return apiClient.get('/admin/search/preferences');
  },
  updatePreferences(data) {
    return apiClient.put('/admin/search/preferences', data);
  },

  // 仪表盘
  getDashboard() {
    return apiClient.get('/admin/search/dashboard');
  },
};
