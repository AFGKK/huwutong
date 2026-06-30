import apiClient from './client';

export default {
  // ─── 仪表盘 ───
  getDashboard() {
    return apiClient.get('/admin/report-scheduler/dashboard');
  },

  // ─── 调度管理 ───
  getSchedules(params = {}) {
    return apiClient.get('/admin/report-scheduler/schedules', { params });
  },

  getSchedulableReports() {
    return apiClient.get('/admin/report-scheduler/schedulable-reports');
  },

  createSchedule(data) {
    return apiClient.post('/admin/report-scheduler/schedules', data);
  },

  updateSchedule(id, data) {
    return apiClient.put(`/admin/report-scheduler/schedules/${id}`, data);
  },

  deleteSchedule(id) {
    return apiClient.delete(`/admin/report-scheduler/schedules/${id}`);
  },

  toggleSchedule(id) {
    return apiClient.post(`/admin/report-scheduler/schedules/${id}/toggle`);
  },

  triggerSchedule(id) {
    return apiClient.post(`/admin/report-scheduler/schedules/${id}/trigger`);
  },

  // ─── 投递日志 ───
  getDeliveryLogs(params = {}) {
    return apiClient.get('/admin/report-scheduler/delivery-logs', { params });
  },
};
