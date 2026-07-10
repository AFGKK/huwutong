import apiClient from '@/api/client';

export default {
  // ─── Changelog 管理 ───

  list(params = {}) {
    return apiClient.get('/admin/changelog', { params });
  },

  show(id) {
    return apiClient.get(`/admin/changelog/${id}`);
  },

  create(data) {
    return apiClient.post('/admin/changelog', data);
  },

  update(id, data) {
    return apiClient.put(`/admin/changelog/${id}`, data);
  },

  delete(id) {
    return apiClient.delete(`/admin/changelog/${id}`);
  },

  stats() {
    return apiClient.get('/admin/changelog/stats');
  },

  // ─── 自动生成 ───

  autoGenerate(apiVersionId) {
    return apiClient.post('/admin/changelog/auto-generate', { api_version_id: apiVersionId });
  },

  createSnapshot(apiVersionId, versionLabel = null) {
    return apiClient.post('/admin/changelog/create-snapshot', {
      api_version_id: apiVersionId,
      version_label: versionLabel,
    });
  },

  autoDetectHistory() {
    return apiClient.get('/admin/changelog/auto-detect-history');
  },

  // ─── 迁移指南 ───

  migrationGuide(fromVersion, toVersion) {
    return apiClient.post('/admin/changelog/migration-guide', {
      from_version: fromVersion,
      to_version: toVersion,
    });
  },

  // ─── 公开 API ───

  publicList(params = {}) {
    return apiClient.get('/changelog', { params });
  },

  publicLatest() {
    return apiClient.get('/changelog/latest');
  },

  publicByVersion() {
    return apiClient.get('/changelog/versions');
  },
};
