import apiClient from './client';

export default {
  // ─── 数据源 ───
  getDataSources() {
    return apiClient.get('/admin/report-builder/data-sources');
  },

  // ─── 主仪表盘 ───
  getDashboard() {
    return apiClient.get('/admin/report-builder/dashboard');
  },

  // ─── 报表 CRUD ───
  getReports(params = {}) {
    return apiClient.get('/admin/report-builder/reports', { params });
  },

  getReport(id) {
    return apiClient.get(`/admin/report-builder/reports/${id}`);
  },

  createReport(data) {
    return apiClient.post('/admin/report-builder/reports', data);
  },

  updateReport(id, data) {
    return apiClient.put(`/admin/report-builder/reports/${id}`, data);
  },

  deleteReport(id) {
    return apiClient.delete(`/admin/report-builder/reports/${id}`);
  },

  // ─── 报表生成 ───
  generateReport(id) {
    return apiClient.post(`/admin/report-builder/reports/${id}/generate`);
  },

  saveSnapshot(id) {
    return apiClient.post(`/admin/report-builder/reports/${id}/snapshot`);
  },

  getSnapshots(id) {
    return apiClient.get(`/admin/report-builder/reports/${id}/snapshots`);
  },

  exportReport(id, format = 'csv') {
    return apiClient.post(`/admin/report-builder/reports/${id}/export`, { format });
  },

  // ─── 仪表盘 ───
  getDashboards() {
    return apiClient.get('/admin/report-builder/dashboards');
  },

  createDashboard(data) {
    return apiClient.post('/admin/report-builder/dashboards', data);
  },

  updateDashboard(id, data) {
    return apiClient.put(`/admin/report-builder/dashboards/${id}`, data);
  },

  deleteDashboard(id) {
    return apiClient.delete(`/admin/report-builder/dashboards/${id}`);
  },
};
