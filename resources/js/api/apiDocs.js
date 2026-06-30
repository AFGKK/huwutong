import apiClient from './client';

export default {
  // 仪表盘
  getDashboard() {
    return apiClient.get('/admin/api-docs/dashboard');
  },

  // 分组
  getGroups() {
    return apiClient.get('/admin/api-docs/groups');
  },

  // 标签
  getTags() {
    return apiClient.get('/admin/api-docs/tags');
  },

  // 端点 CRUD
  getEndpoints(params = {}) {
    return apiClient.get('/admin/api-docs/endpoints', { params });
  },

  getEndpoint(id) {
    return apiClient.get(`/admin/api-docs/endpoints/${id}`);
  },

  createEndpoint(data) {
    return apiClient.post('/admin/api-docs/endpoints', data);
  },

  updateEndpoint(id, data) {
    return apiClient.put(`/admin/api-docs/endpoints/${id}`, data);
  },

  deleteEndpoint(id) {
    return apiClient.delete(`/admin/api-docs/endpoints/${id}`);
  },

  // Schema
  getSchemas() {
    return apiClient.get('/admin/api-docs/schemas');
  },

  // 代码片段
  addSnippet(data) {
    return apiClient.post('/admin/api-docs/snippets', data);
  },
  deleteSnippet(id) {
    return apiClient.delete(`/admin/api-docs/snippets/${id}`);
  },

  // 测试控制台
  sendTestRequest(data) {
    return apiClient.post('/admin/api-docs/test', data);
  },
  getTestHistory() {
    return apiClient.get('/admin/api-docs/test-history');
  },

  // SDK
  getSdks() {
    return apiClient.get('/admin/api-docs/sdks');
  },
  generateSdk(language, endpoint_ids = []) {
    return apiClient.post(`/admin/api-docs/sdks/generate/${language}`, { endpoint_ids });
  },

  // 变更日志
  getChangelogs(params = {}) {
    return apiClient.get('/admin/api-docs/changelogs', { params });
  },
  createChangelog(data) {
    return apiClient.post('/admin/api-docs/changelogs', data);
  },

  // 路由扫描
  scanRoutes(api_version_id = null) {
    return apiClient.post('/admin/api-docs/scan-routes', { api_version_id });
  },

  // 版本差异对比
  versionDiff(data) {
    return apiClient.post('/admin/api-docs/version-diff', data);
  },

  // ─── M3-09 增强功能 ───

  // 端点收藏
  toggleFavorite(endpoint_id, note = null) {
    return apiClient.post('/admin/api-docs/favorites/toggle', { endpoint_id, note });
  },
  getFavorites() {
    return apiClient.get('/admin/api-docs/favorites');
  },

  // OpenAPI 导出
  exportOpenApi(api_version_id = null) {
    return apiClient.get('/admin/api-docs/export/openapi', { params: { api_version_id } });
  },

  // 自动生成代码片段
  autoGenerateSnippets(endpoint_id) {
    return apiClient.post('/admin/api-docs/snippets/auto-generate', { endpoint_id });
  },

  // 批量更新端点状态
  batchUpdateEndpoints(endpoint_ids, status) {
    return apiClient.post('/admin/api-docs/endpoints/batch-update', { endpoint_ids, status });
  },

  // 端点统计
  getEndpointStats(id) {
    return apiClient.get(`/admin/api-docs/endpoints/${id}/stats`);
  },

  // ─── M3-32 Changelog 自动生成 ───
  autoDetectChanges(api_version_id) {
    return apiClient.post('/admin/api-docs/auto-detect-changes', { api_version_id });
  },
  createSnapshot(api_version_id, version_label = null) {
    return apiClient.post('/admin/api-docs/create-snapshot', { api_version_id, version_label });
  },
  getAutoDetectHistory() {
    return apiClient.get('/admin/api-docs/auto-detect-history');
  },
};

// API 版本管理
export function getApiVersions() {
  return apiClient.get('/api-versions');
}

// 公开 API 文档（无需认证）
export function getPublicApiDocs(params = {}) {
  return apiClient.get('/api-docs/public', { params });
}
