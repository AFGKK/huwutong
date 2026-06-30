import apiClient from './client';

export default {
  // ─── Dashboard ──────────────────────────────────────────────
  getDashboard() {
    return apiClient.get('/admin/i18n/dashboard');
  },

  // ─── Languages ──────────────────────────────────────────────
  getLanguages() {
    return apiClient.get('/admin/i18n/languages');
  },

  createLanguage(data) {
    return apiClient.post('/admin/i18n/languages', data);
  },

  updateLanguage(id, data) {
    return apiClient.put(`/admin/i18n/languages/${id}`, data);
  },

  deleteLanguage(id) {
    return apiClient.delete(`/admin/i18n/languages/${id}`);
  },

  // ─── Namespaces ─────────────────────────────────────────────
  getNamespaces() {
    return apiClient.get('/admin/i18n/namespaces');
  },

  createNamespace(data) {
    return apiClient.post('/admin/i18n/namespaces', data);
  },

  deleteNamespace(id) {
    return apiClient.delete(`/admin/i18n/namespaces/${id}`);
  },

  // ─── Translations ───────────────────────────────────────────
  getTranslations(params = {}) {
    return apiClient.get('/admin/i18n/translations', { params });
  },

  getTranslation(id) {
    return apiClient.get(`/admin/i18n/translations/${id}`);
  },

  updateTranslation(id, data) {
    return apiClient.put(`/admin/i18n/translations/${id}`, data);
  },

  bulkUpdateTranslations(data) {
    return apiClient.post('/admin/i18n/translations/bulk-update', data);
  },

  publishTranslation(id, isPublished) {
    return apiClient.post(`/admin/i18n/translations/${id}/publish`, { is_published: isPublished });
  },

  // ─── Scan ───────────────────────────────────────────────────
  scanPhpFiles(locale = null) {
    return apiClient.post('/admin/i18n/scan', { locale });
  },

  // ─── Export ─────────────────────────────────────────────────
  exportTranslations(data) {
    return apiClient.post('/admin/i18n/export', data, { responseType: 'blob' });
  },

  // ─── Import ─────────────────────────────────────────────────
  importTranslations(formData) {
    return apiClient.post('/admin/i18n/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  getImportHistory() {
    return apiClient.get('/admin/i18n/import-history');
  },

  // ─── Auto-translate (legacy) ────────────────────────────────
  autoTranslateAll(data) {
    return apiClient.post('/admin/i18n/auto-translate', data);
  },

  autoTranslateSingle(id) {
    return apiClient.post(`/admin/i18n/auto-translate/${id}`);
  },

  // ─── M3-85 翻译引擎 API ─────────────────────────────────────
  engineTranslateSingle(id) {
    return apiClient.post(`/admin/i18n/engine/translate/${id}`);
  },

  engineTranslateMissing(data) {
    return apiClient.post('/admin/i18n/engine/translate-missing', data);
  },

  assessQuality(id) {
    return apiClient.get(`/admin/i18n/engine/quality/${id}`);
  },

  getMemoryStats() {
    return apiClient.get('/admin/i18n/engine/memory-stats');
  },

  translateBatch(data) {
    return apiClient.post('/admin/i18n/engine/translate-batch', data);
  },
};
