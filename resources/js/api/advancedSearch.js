import apiClient from './client';

export default {
  // ─── 筛选器定义 ───

  /** 获取所有页面的筛选器定义 */
  getAllFilterDefinitions() {
    return apiClient.get('/advanced-search/filters');
  },

  /** 获取指定页面的筛选器定义 */
  getFilterDefinitions(page) {
    return apiClient.get(`/advanced-search/filters/${page}`);
  },

  // ─── 高级搜索 ───

  /** 执行高级筛选搜索 */
  advancedSearch(page, filters, options = {}) {
    return apiClient.post(`/advanced-search/search/${page}`, {
      filters,
      sort: options.sort,
      page: options.page || 1,
      per_page: options.perPage || 20,
      columns: options.columns,
    });
  },

  // ─── 保存搜索（增强） ───

  /** 获取我的保存搜索列表 */
  getMySavedSearches(params = {}) {
    return apiClient.get('/advanced-search/saved', { params });
  },

  /** 创建保存搜索 */
  createSavedSearch(data) {
    return apiClient.post('/advanced-search/saved', data);
  },

  /** 更新保存搜索 */
  updateSavedSearch(id, data) {
    return apiClient.put(`/advanced-search/saved/${id}`, data);
  },

  /** 删除保存搜索 */
  deleteSavedSearch(id) {
    return apiClient.delete(`/advanced-search/saved/${id}`);
  },

  /** 应用保存的搜索（记录使用） */
  applySavedSearch(id) {
    return apiClient.post(`/advanced-search/saved/${id}/apply`);
  },

  /** 获取共享的搜索 */
  getSharedSearches(params = {}) {
    return apiClient.get('/advanced-search/saved/shared', { params });
  },

  /** 获取常用搜索 */
  getFrequentSearches(limit = 5) {
    return apiClient.get('/advanced-search/saved/frequent', { params: { limit } });
  },
};
