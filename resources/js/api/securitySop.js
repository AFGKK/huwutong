import apiClient from './client';

export default {
  getSopTemplates(params = {}) {
    return apiClient.get('/admin/security/sop-templates', { params });
  },
  createSopTemplate(data) {
    return apiClient.post('/admin/security/sop-templates', data);
  },
  getSopTemplate(id) {
    return apiClient.get(`/admin/security/sop-templates/${id}`);
  },
  updateSopTemplate(id, data) {
    return apiClient.put(`/admin/security/sop-templates/${id}`, data);
  },
  deleteSopTemplate(id) {
    return apiClient.delete(`/admin/security/sop-templates/${id}`);
  },
  executeSop(id, event_id = null) {
    return apiClient.post(`/admin/security/sop-templates/${id}/execute`, { event_id });
  },
  handleEventSop(eventId) {
    return apiClient.post(`/admin/security/events/${eventId}/handle-sop`);
  },
  resolveEvent(eventId, resolution, notes = null) {
    return apiClient.post(`/admin/security/events/${eventId}/resolve`, { resolution, notes });
  },
  getSopExecutions(params = {}) {
    return apiClient.get('/admin/security/sop-executions', { params });
  },
  getSopStats() {
    return apiClient.get('/admin/security/sop-stats');
  },
};
