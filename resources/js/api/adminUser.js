import apiClient from './client';

export default {
  list(params) {
    return apiClient.get('/admin/users', { params });
  },
  show(id) {
    return apiClient.get(`/admin/users/${id}`);
  },
  create(data) {
    return apiClient.post('/admin/users', data);
  },
  update(id, data) {
    return apiClient.put(`/admin/users/${id}`, data);
  },
  destroy(id) {
    return apiClient.delete(`/admin/users/${id}`);
  },
  resetPassword(id, data) {
    return apiClient.post(`/admin/users/${id}/reset-password`, data);
  },
  toggleStatus(id) {
    return apiClient.post(`/admin/users/${id}/toggle-status`);
  },
  stats() {
    return apiClient.get('/admin/users/stats');
  },
};
