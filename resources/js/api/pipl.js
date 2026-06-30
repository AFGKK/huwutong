import apiClient from '@/api/client';

export default {
  getStats() {
    return apiClient.get('/pipl/stats');
  },
  getSensitiveFields() {
    return apiClient.get('/pipl/sensitive-fields');
  },
  scan(data = {}) {
    return apiClient.post('/pipl/scan', data);
  },
  getInventory(params = {}) {
    return apiClient.get('/pipl/inventory', { params });
  },
  updateInventory(id, data) {
    return apiClient.put(`/pipl/inventory/${id}`, data);
  },
  batchUpdateInventory(data) {
    return apiClient.post('/pipl/inventory/batch-update', data);
  },
  getCrossBorderTransfers(params = {}) {
    return apiClient.get('/pipl/cross-border-transfers', { params });
  },
  createCrossBorderTransfer(data) {
    return apiClient.post('/pipl/cross-border-transfers', data);
  },
  updateCrossBorderTransfer(id, data) {
    return apiClient.put(`/pipl/cross-border-transfers/${id}`, data);
  },
  reviewCrossBorderTransfer(id, data) {
    return apiClient.post(`/pipl/cross-border-transfers/${id}/review`, data);
  },
  getDpias(params = {}) {
    return apiClient.get('/pipl/dpias', { params });
  },
  getDpia(id) {
    return apiClient.get(`/pipl/dpias/${id}`);
  },
  createDpia(data) {
    return apiClient.post('/pipl/dpias', data);
  },
  updateDpia(id, data) {
    return apiClient.put(`/pipl/dpias/${id}`, data);
  },
  completeDpia(id, data) {
    return apiClient.post(`/pipl/dpias/${id}/complete`, data);
  },
  // M3-33b 增强
  getDpo() {
    return apiClient.get('/pipl/dpo');
  },
  updateDpo(data) {
    return apiClient.put('/pipl/dpo', data);
  },
  checkMinor(data) {
    return apiClient.post('/pipl/check-minor', data);
  },
  createBreachNotification(data) {
    return apiClient.post('/pipl/breach-notifications', data);
  },
  getEnhancedStats() {
    return apiClient.get('/pipl/enhanced-stats');
  },
};
