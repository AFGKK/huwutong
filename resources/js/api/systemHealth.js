import apiClient from './client';

export default {
  // ─── 仪表盘 ───
  getDashboard() {
    return apiClient.get('/admin/system-health/dashboard');
  },

  // ─── 实时检查 ───
  runCheck() {
    return apiClient.get('/admin/system-health/check');
  },

  // ─── 趋势 ───
  getTrend(period = '24h') {
    return apiClient.get('/admin/system-health/trend', { params: { period } });
  },

  // ─── 快照 ───
  takeSnapshot() {
    return apiClient.post('/admin/system-health/snapshot');
  },

  // ─── 阈值 ───
  getThresholds() {
    return apiClient.get('/admin/system-health/thresholds');
  },

  updateThreshold(id, data) {
    return apiClient.put(`/admin/system-health/thresholds/${id}`, data);
  },

  // ─── 失败任务 ───
  getFailedJobs() {
    return apiClient.get('/admin/system-health/failed-jobs');
  },
};
