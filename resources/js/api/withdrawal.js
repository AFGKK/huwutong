import client from './client';

export default {
  // ── 管理端 ──
  index(params = {}) {
    return client.get('/admin/withdrawals', { params });
  },
  show(id) {
    return client.get(`/admin/withdrawals/${id}`);
  },
  review(id, data) {
    return client.post(`/admin/withdrawals/${id}/review`, data);
  },
  markCompleted(id, data) {
    return client.post(`/admin/withdrawals/${id}/completed`, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  markFailed(id, data) {
    return client.post(`/admin/withdrawals/${id}/failed`, data);
  },
  uploadProof(id, formData) {
    return client.post(`/admin/withdrawals/${id}/proof`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  stats() {
    return client.get('/admin/withdrawals/stats');
  },

  // ── 批次管理 ──
  batches(params = {}) {
    return client.get('/admin/withdrawals/batches', { params });
  },
  showBatch(id) {
    return client.get(`/admin/withdrawals/batches/${id}`);
  },
  createBatch(data) {
    return client.post('/admin/withdrawals/batches', data);
  },
  completeBatch(id, data) {
    return client.post(`/admin/withdrawals/batches/${id}/complete`, data);
  },

  // ── 渠道配置 ──
  channels() {
    return client.get('/admin/withdrawals/channels');
  },

  // ── 用户端 ──
  myWithdrawals(params = {}) {
    return client.get('/withdrawals', { params });
  },
  myStats() {
    return client.get('/withdrawals/stats');
  },
  userChannels() {
    return client.get('/withdrawals/channels');
  },
  requestWithdrawal(data) {
    return client.post('/withdrawals', data);
  },
  cancelWithdrawal(id) {
    return client.post(`/withdrawals/${id}/cancel`);
  },

  // ── M3-72 增强 ──
  retry(id) {
    return client.post(`/admin/withdrawals/${id}/retry`);
  },
  batchRetry(ids) {
    return client.post('/admin/withdrawals/batch-retry', { ids });
  },
  releasePending() {
    return client.post('/admin/withdrawals/release-pending');
  },
  riskCheck(params) {
    return client.get('/admin/withdrawals/risk-check', { params });
  },
};
