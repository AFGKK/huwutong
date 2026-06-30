import apiClient from './client';

export default {
  getDashboard() {
    return apiClient.get('/csm/dashboard');
  },
  getCustomers(params = {}) {
    return apiClient.get('/csm/customers', { params });
  },
  getCustomerDetail(id) {
    return apiClient.get(`/csm/customers/${id}`);
  },
  calculateHealth(id) {
    return apiClient.post(`/csm/customers/${id}/calculate-health`);
  },
  batchCalculateHealth() {
    return apiClient.post('/csm/batch-calculate-health');
  },
  getTasks(params = {}) {
    return apiClient.get('/csm/tasks', { params });
  },
  createTask(data) {
    return apiClient.post('/csm/tasks', data);
  },
  updateTask(id, data) {
    return apiClient.put(`/csm/tasks/${id}`, data);
  },
  deleteTask(id) {
    return apiClient.delete(`/csm/tasks/${id}`);
  },
  getCommunications(params = {}) {
    return apiClient.get('/csm/communications', { params });
  },
  createCommunication(data) {
    return apiClient.post('/csm/communications', data);
  },
  createRenewalReminders() {
    return apiClient.post('/csm/create-renewal-reminders');
  },
  getHealthTrend(params = {}) {
    return apiClient.get('/csm/health-trend', { params });
  },
  getRenewalCalendar(params = {}) {
    return apiClient.get('/csm/renewal-calendar', { params });
  },
  getActivityTimeline(params = {}) {
    return apiClient.get('/csm/activity-timeline', { params });
  },
};
